<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\BiteshipService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dp_dibayar' => 'DP Dibayar',
        'produksi' => 'Produksi',
        'siap_kirim' => 'Siap Kirim',
        'lunas' => 'Lunas',
        'dikirim' => 'Dikirim',
        'delivered' => 'Delivered',
        'dibatalkan_buyer' => 'Dibatalkan Buyer',
        'dibatalkan_sistem' => 'Dibatalkan Sistem',
    ];

    public function __construct(
        private OrderService $orderService,
        private BiteshipService $biteshipService,
    ) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($search !== '', fn (Builder $query): Builder => $query->where('order_number', 'like', '%'.$search.'%'))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/orders/index', [
            'orders' => [
                'data' => $orders->getCollection()->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'payment_mode' => $order->payment_mode->value,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'items_count' => $order->items_count,
                    'user' => [
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->user->phone,
                    ],
                ])->values()->all(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'links' => $orders->linkCollection()->toArray(),
            ],
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['user', 'address', 'items.campaign', 'payments', 'statusHistories']);

        return Inertia::render('admin/orders/show', $this->orderShowProps($order));
    }

    public function label(Order $order): View
    {
        $order->load(['items.campaign', 'address', 'user']);

        abort_unless(filled($order->resi_number), 404);

        return view('labels.order', [
            'order' => $order,
        ]);
    }

    public function courierRates(Request $request, Order $order): Response|RedirectResponse
    {
        $order->load(['user', 'address', 'items.campaign', 'payments', 'statusHistories']);

        if ($order->status !== OrderStatus::Lunas || $order->address === null) {
            return back()->withErrors(['courier' => 'Kurir hanya bisa dihitung untuk order lunas dengan alamat pengiriman.']);
        }

        try {
            $rates = $this->biteshipService->getRates(
                $this->shippingItems($order),
                $order->address->postal_code,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['courier' => $exception->getMessage()]);
        }

        return Inertia::render('admin/orders/show', $this->orderShowProps($order, $rates));
    }

    public function bookCourier(Request $request, Order $order): RedirectResponse
    {
        $order->load(['address', 'items.campaign', 'payments', 'statusHistories']);

        if ($order->status !== OrderStatus::Lunas || $order->address === null) {
            return back()->withErrors(['courier' => 'Kurir hanya bisa di-book untuk order lunas dengan alamat pengiriman.']);
        }

        $data = $request->validate([
            'courier_code' => ['required', 'string'],
            'courier_service' => ['required', 'string'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $rates = $this->biteshipService->getRates(
                $this->shippingItems($order),
                $order->address->postal_code,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['courier' => $exception->getMessage()]);
        }

        $rate = collect($rates)->first(fn (array $rate): bool => $rate['courier_code'] === $data['courier_code']
            && $rate['courier_service_code'] === $data['courier_service']);

        if ($rate === null) {
            return back()->withErrors(['courier' => 'Kurir tidak valid, hitung ulang ongkir.']);
        }

        $shippingCost = $rate['price'];

        try {
            DB::transaction(function () use ($data, $order, $shippingCost): void {
                $result = $this->biteshipService->bookCourier(
                    $order,
                    $data['courier_code'],
                    $data['courier_service'],
                    [
                        'address' => $order->address,
                        'note' => $data['note'] ?? null,
                    ],
                );
                $waybillId = $result['waybill_id'] ?? null;
                $order->forceFill([
                    'biteship_order_id' => $result['order_id'],
                    'resi_number' => $waybillId,
                    'courier_code' => $data['courier_code'],
                    'courier_service' => $data['courier_service'],
                    'shipping_cost' => $shippingCost,
                    'shipped_at' => now(),
                ])->save();
                $this->orderService->markDikirim(
                    $order,
                    'Booking kurir '.strtoupper($data['courier_code']).' - resi '.(string) $waybillId,
                );
            });
        } catch (DomainException $exception) {
            return back()->withErrors(['courier' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Kurir di-book, resi: '.($order->resi_number ?? 'menunggu resi').'.');
    }

    public function transition(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $status = OrderStatus::from($data['status']);

        if (! in_array($status->value, $this->orderService->allowedTransitions($order), true)) {
            return back()->withErrors(['status' => 'Transisi tidak valid.']);
        }

        try {
            $this->orderService->transition($order, $status, $data['note'] ?? null);
        } catch (DomainException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return back()->with('status', 'Status order diperbarui.');
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $courierRates
     * @return array<string, mixed>
     */
    private function orderShowProps(Order $order, ?array $courierRates = null): array
    {
        $canPayRemaining = $this->canPayRemaining($order);

        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_mode' => $order->payment_mode->value,
                'subtotal' => $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'coupon_code' => $order->coupon_code,
                'dp_amount' => $order->dp_amount,
                'total_amount' => $order->total_amount,
                'payment_due_at' => $order->payment_due_at,
                'buyer_notes' => $order->buyer_notes,
                'created_at' => $order->created_at,
                'user' => [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ],
                'items' => $order->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->campaign->title,
                    'size' => $item->size->value,
                    'qty' => $item->qty,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                ])->values()->all(),
                'payments' => $order->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'payment_type' => $payment->payment_type->value,
                    'payment_status' => $payment->payment_status->value,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at,
                    'midtrans_order_id' => $payment->midtrans_order_id,
                ])->values()->all(),
                'histories' => $order->statusHistories->map(fn (OrderStatusHistory $history): array => [
                    'id' => $history->id,
                    'status' => $history->status->value,
                    'note' => $history->note,
                    'created_at' => $history->created_at,
                ])->values()->all(),
            ],
            'statuses' => $this->statusOptions($this->orderService->allowedTransitions($order)),
            'can_pay_remaining' => $canPayRemaining,
            'remaining_amount' => $canPayRemaining ? $this->remainingAmount($order) : null,
            'address' => $this->addressData($order->address),
            'courier_rates' => $courierRates,
        ];
    }

    /**
     * @return array<int, array{name: string, value: int, quantity: int, weight: int}>
     */
    private function shippingItems(Order $order): array
    {
        return $order->items->map(fn (OrderItem $item): array => [
            'name' => $item->campaign->title.' - '.$item->size->value,
            'value' => (int) round((float) $item->unit_price),
            'quantity' => $item->qty,
            'weight' => (int) config('biteship.item_weight_grams', 250),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function addressData(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'id' => $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'province' => $address->province,
            'city' => $address->city,
            'district' => $address->district,
            'subdistrict' => $address->subdistrict,
            'postal_code' => $address->postal_code,
            'full_address' => $address->full_address,
        ];
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(?array $values = null): array
    {
        $values ??= array_keys(self::STATUS_LABELS);

        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => self::STATUS_LABELS[$value],
        ], $values);
    }

    private function canPayRemaining(Order $order): bool
    {
        return $order->status === OrderStatus::SiapKirim
            && ! $order->payments->contains(
                fn (Payment $payment): bool => $payment->payment_type === PaymentType::Pelunasan
                    && $payment->payment_status === PaymentStatus::Sukses,
            );
    }

    private function remainingAmount(Order $order): string
    {
        return number_format(
            max(0, (float) $order->total_amount - (float) $order->dp_amount),
            2,
            '.',
            '',
        );
    }
}
