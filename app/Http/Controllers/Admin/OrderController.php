<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Services\OrderService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function __construct(private OrderService $orderService) {}

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
        $order->load(['user', 'items.campaign', 'payments', 'statusHistories']);
        $canPayRemaining = $this->canPayRemaining($order);

        return Inertia::render('admin/orders/show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_mode' => $order->payment_mode->value,
                'subtotal' => $order->subtotal,
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
        ]);
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
