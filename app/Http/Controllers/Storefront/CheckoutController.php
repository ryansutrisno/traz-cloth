<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\BiteshipService;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\MidtransService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService,
        private OrderService $orderService,
        private MidtransService $midtransService,
        private BiteshipService $biteshipService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = $this->cartService->itemsFor($user);
        $subtotal = $this->subtotal($items);

        return Inertia::render('checkout/index', $this->checkoutProps($user, $items, $subtotal));
    }

    public function rates(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
        ]);
        $address = Address::query()->findOrFail($data['address_id']);
        $this->ensureAddressOwnership($request, $address);
        $user = $request->user();
        $items = $this->cartService->itemsFor($user);

        if ($items->isEmpty()) {
            return back()->withErrors(['checkout' => 'Keranjang belanja kosong.']);
        }

        try {
            $rates = $this->biteshipService->getRates(
                $this->shippingItems($items),
                $address->postal_code,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['courier' => $exception->getMessage()]);
        }

        $subtotal = $this->subtotal($items);

        return Inertia::render('checkout/index', $this->checkoutProps(
            $user,
            $items,
            $subtotal,
            $rates,
            $address->id,
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in([PaymentMode::Dp->value, PaymentMode::Lunas->value])],
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'courier_code' => ['required', 'string'],
            'courier_service' => ['required', 'string'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
        ]);
        $address = Address::query()->findOrFail($data['address_id']);
        $this->ensureAddressOwnership($request, $address);
        $user = $request->user();
        $items = $this->cartService->itemsFor($user);

        if ($items->isEmpty()) {
            return back()->withErrors(['checkout' => 'Keranjang belanja kosong.']);
        }

        try {
            $rates = $this->biteshipService->getRates(
                $this->shippingItems($items),
                $address->postal_code,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['courier' => $exception->getMessage()]);
        }

        $rate = collect($rates)->first(fn (array $rate): bool => $rate['courier_code'] === $data['courier_code']
            && $rate['courier_service_code'] === $data['courier_service']);

        if ($rate === null) {
            return back()->withErrors(['courier' => 'Kurir tidak valid, hitung ulang ongkir.']);
        }

        $paymentMode = PaymentMode::from($data['payment_mode']);
        $coupon = null;
        $couponCode = (string) session('checkout_coupon', '');

        if ($couponCode !== '') {
            try {
                $coupon = $this->couponService->resolve($couponCode, $user, (float) $this->subtotal($items));
            } catch (DomainException $exception) {
                session()->forget('checkout_coupon');

                return back()->withErrors(['coupon' => $exception->getMessage()]);
            }
        }

        try {
            $order = $this->orderService->createFromCart($user, $paymentMode, [
                'address_id' => $address->id,
                'courier_code' => $data['courier_code'],
                'courier_service' => $data['courier_service'],
                'shipping_cost' => $rate['price'],
            ], $coupon);
        } catch (DomainException $exception) {
            if ($coupon !== null) {
                try {
                    $this->couponService->resolve($coupon->code, $user, (float) $this->subtotal($items));
                } catch (DomainException $couponException) {
                    session()->forget('checkout_coupon');

                    return back()->withErrors(['coupon' => $couponException->getMessage()]);
                }
            }

            return back()->withErrors(['checkout' => 'Kuota tidak cukup atau kampanye tidak tersedia lagi.']);
        }

        $paymentType = $paymentMode === PaymentMode::Dp ? PaymentType::Dp : PaymentType::Full;
        $amount = $paymentType === PaymentType::Dp ? $order->dp_amount : $order->total_amount;
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'payment_type' => $paymentType,
            'payment_status' => PaymentStatus::Menunggu,
            'amount' => $amount,
            'midtrans_order_id' => $order->order_number,
        ]);

        $snap = $this->midtransService->createSnapToken($order, $payment->payment_type, [
            'finish' => route('checkout.success', $order),
            'error' => route('checkout.canceled'),
            'unfinish' => route('checkout.canceled'),
        ]);

        if (str_starts_with($snap['token'], 'dev-')) {
            return redirect()
                ->route('checkout.dev', $order)
                ->with('dev_token', $snap['token']);
        }

        return redirect()->away($snap['redirect_url']);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);
        $user = $request->user();
        $items = $this->cartService->itemsFor($user);

        try {
            $coupon = $this->couponService->resolve($data['code'], $user, (float) $this->subtotal($items));
        } catch (DomainException $exception) {
            return back()->withErrors(['coupon' => $exception->getMessage()]);
        }

        session(['checkout_coupon' => $coupon->code]);

        return back()->with('success', 'Kupon berhasil dipakai.');
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        session()->forget('checkout_coupon');

        return back()->with('success', 'Kupon dihapus.');
    }

    public function dev(Request $request, Order $order): Response
    {
        $this->ensureDevelopmentMode();
        $this->ensureOwnership($request, $order);

        return Inertia::render('checkout/dev', [
            'order' => $this->orderData($order),
        ]);
    }

    public function devPay(Request $request, Order $order): RedirectResponse
    {
        $this->ensureDevelopmentMode();
        $this->ensureOwnership($request, $order);

        if ($order->status !== OrderStatus::MenungguPembayaran) {
            return redirect()->route('checkout.success', $order);
        }

        $paymentType = $order->payment_mode === PaymentMode::Dp ? PaymentType::Dp : PaymentType::Full;

        DB::transaction(function () use ($order, $paymentType): void {
            $payment = $order->payments()
                ->where('payment_type', $paymentType->value)
                ->firstOrFail();
            $payment->update([
                'payment_status' => PaymentStatus::Sukses,
                'paid_at' => now(),
                'midtrans_transaction_id' => 'dev-'.$order->order_number,
            ]);

            if ($paymentType === PaymentType::Dp) {
                $this->orderService->markDpPaid($order, $payment);
            } else {
                $this->orderService->markLunas($order, $payment);
            }
        });

        return redirect()->route('checkout.success', $order);
    }

    public function success(Request $request, Order $order): Response
    {
        $this->ensureOwnership($request, $order);

        return Inertia::render('checkout/success', [
            'order' => $this->orderData($order),
        ]);
    }

    public function canceled(): RedirectResponse
    {
        return redirect()
            ->route('cart.index')
            ->withErrors(['checkout' => 'Pembayaran dibatalkan.']);
    }

    /**
     * @param  iterable<int, CartItem>  $items
     * @return array<string, mixed>
     */
    private function checkoutProps(User $user, iterable $items, int $subtotal, ?array $rates = null, ?int $selectedAddressId = null): array
    {
        $coupon = null;
        $discountAmount = 0.0;
        $couponCode = (string) session('checkout_coupon', '');

        if ($couponCode !== '') {
            try {
                $coupon = $this->couponService->resolve($couponCode, $user, (float) $subtotal);
                $discountAmount = $this->couponService->discountFor($coupon, (float) $subtotal);
            } catch (DomainException) {
                session()->forget('checkout_coupon');
            }
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'coupon' => $coupon === null ? null : [
                'code' => $coupon->code,
                'description' => $coupon->description,
                'type' => $coupon->type->value,
                'type_label' => $coupon->type->label(),
                'value' => (float) $coupon->value,
            ],
            'dp_amount' => (int) round(($subtotal - $discountAmount) * 0.5),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'payment_modes' => [PaymentMode::Dp->value, PaymentMode::Lunas->value],
            'addresses' => $user->addresses()
                ->get(['id', 'label', 'recipient_name', 'phone', 'province', 'city', 'district', 'subdistrict', 'postal_code', 'full_address', 'is_default']),
            'courier_rates' => $rates,
            'selected_address_id' => $selectedAddressId,
        ];
    }

    /**
     * @param  iterable<int, CartItem>  $items
     * @return array<int, array{name: string, value: int, quantity: int, weight: int}>
     */
    private function shippingItems(iterable $items): array
    {
        $shippingItems = [];

        foreach ($items as $item) {
            $shippingItems[] = [
                'name' => $item->campaign->title.' - '.$item->size->value,
                'value' => (int) $item->getAttribute('price'),
                'quantity' => $item->qty,
                'weight' => (int) config('biteship.item_weight_grams', 250),
            ];
        }

        return $shippingItems;
    }

    /**
     * @param  iterable<int, CartItem>  $items
     */
    private function subtotal(iterable $items): int
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += (int) $item->getAttribute('subtotal');
        }

        return $subtotal;
    }

    private function ensureAddressOwnership(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 403);
    }

    private function ensureDevelopmentMode(): void
    {
        abort_if(trim((string) config('midtrans.server_key', '')) !== '', 404);
    }

    private function ensureOwnership(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    /**
     * @return array{order_number: string, payment_mode: string, total_amount: string, dp_amount: string|null, status: string}
     */
    private function orderData(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'payment_mode' => $order->payment_mode->value,
            'total_amount' => $order->total_amount,
            'dp_amount' => $order->dp_amount,
            'status' => $order->status->value,
        ];
    }
}
