<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CartService;
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
        private OrderService $orderService,
        private MidtransService $midtransService,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = $this->cartService->itemsFor($user);
        $subtotal = $items->sum(fn (CartItem $item): int => (int) $item->getAttribute('subtotal'));

        return Inertia::render('checkout/index', [
            'items' => $items,
            'subtotal' => $subtotal,
            'dp_amount' => (int) round($subtotal * 0.5),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'payment_modes' => [PaymentMode::Dp->value, PaymentMode::Lunas->value],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_mode' => ['required', Rule::in([PaymentMode::Dp->value, PaymentMode::Lunas->value])],
        ]);
        $paymentMode = PaymentMode::from($data['payment_mode']);

        try {
            $order = $this->orderService->createFromCart($request->user(), $paymentMode);
        } catch (DomainException $exception) {
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
