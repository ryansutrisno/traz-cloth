<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\MidtransService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private MidtransService $midtransService,
        private OrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('orders/index', [
            'orders' => [
                'data' => $orders->getCollection()->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'payment_mode' => $order->payment_mode->value,
                    'total_amount' => $order->total_amount,
                    'created_at' => $order->created_at,
                    'items_count' => $order->items_count,
                    'resi_number' => $order->resi_number,
                ])->values()->all(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'links' => $orders->linkCollection()->toArray(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->ensureOwnership($request, $order);
        $order->load(['items.campaign', 'payments']);
        $canPayRemaining = $this->canPayRemaining($order);

        return Inertia::render('orders/show', [
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
                'created_at' => $order->created_at,
                'courier_code' => $order->courier_code,
                'courier_service' => $order->courier_service,
                'resi_number' => $order->resi_number,
                'shipped_at' => $order->shipped_at,
                'delivered_at' => $order->delivered_at,
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
                ])->values()->all(),
            ],
            'can_pay_remaining' => $canPayRemaining,
            'remaining_amount' => $canPayRemaining ? $this->remainingAmount($order) : null,
        ]);
    }

    public function payRemaining(Request $request, Order $order): RedirectResponse
    {
        $this->ensureOwnership($request, $order);

        if ($order->status !== OrderStatus::SiapKirim) {
            return back()->withErrors(['order' => 'Pelunasan hanya bisa dilakukan saat barang siap kirim.']);
        }

        if ($this->hasSuccessfulRemainingPayment($order)) {
            return back()->withErrors(['order' => 'Pelunasan sudah dibayar.']);
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'payment_type' => PaymentType::Pelunasan,
            'payment_status' => PaymentStatus::Menunggu,
            'amount' => max(0, (float) $order->total_amount - (float) $order->dp_amount),
            'midtrans_order_id' => $order->order_number.'-2',
        ]);
        $snap = $this->midtransService->createSnapToken($order, PaymentType::Pelunasan, [
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

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->ensureOwnership($request, $order);

        if (in_array($order->status, [
            OrderStatus::Dikirim,
            OrderStatus::Delivered,
            OrderStatus::DibatalkanBuyer,
            OrderStatus::DibatalkanSistem,
        ], true)) {
            return back()->withErrors(['order' => 'Pesanan tidak dapat dibatalkan pada status ini.']);
        }

        try {
            $this->orderService->cancel($order, OrderStatus::DibatalkanBuyer, 'Dibatalkan oleh pembeli');
        } catch (DomainException $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pesanan berhasil dibatalkan.');
    }

    private function ensureOwnership(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    private function canPayRemaining(Order $order): bool
    {
        return $order->status === OrderStatus::SiapKirim
            && ! $this->hasSuccessfulRemainingPayment($order);
    }

    private function hasSuccessfulRemainingPayment(Order $order): bool
    {
        return $order->payments
            ->contains(fn (Payment $payment): bool => $payment->payment_type === PaymentType::Pelunasan
                && $payment->payment_status === PaymentStatus::Sukses);
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
