<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\Size;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class OrderService
{
    /**
     * Allowed forward transitions for operational order processing.
     *
     * @var array<string, array<int, string>>
     */
    protected const array TRANSITIONS = [
        'menunggu_pembayaran' => [
            'dp_dibayar',
            'lunas',
            'dibatalkan_buyer',
            'dibatalkan_sistem',
        ],
        'dp_dibayar' => [
            'produksi',
            'dibatalkan_buyer',
            'dibatalkan_sistem',
        ],
        'produksi' => [
            'siap_kirim',
            'dibatalkan_buyer',
            'dibatalkan_sistem',
        ],
        'siap_kirim' => [
            'lunas',
            'dibatalkan_buyer',
            'dibatalkan_sistem',
        ],
        'lunas' => [
            'dikirim',
            'dibatalkan_buyer',
            'dibatalkan_sistem',
        ],
        'dikirim' => [
            'delivered',
        ],
        'delivered' => [],
    ];

    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
        private CouponService $couponService,
    ) {}

    public function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $orderNumber = 'TRAZ-'.date('Ymd').'-'.strtoupper(Str::random(4));

            if (! Order::query()->where('order_number', $orderNumber)->exists()) {
                return $orderNumber;
            }
        }

        throw new DomainException('Nomor order tidak dapat dibuat.');
    }

    /** @param array<string, mixed> $shipping */
    public function createFromCart(User $user, PaymentMode $paymentMode, array $shipping = [], ?Coupon $coupon = null): Order
    {
        return DB::transaction(function () use ($user, $paymentMode, $shipping, $coupon): Order {
            $cartItems = CartItem::query()
                ->where('user_id', $user->id)
                ->with('campaign.sizes')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw new DomainException('Keranjang belanja kosong.');
            }

            $lineItems = [];
            $requestedByCampaign = [];
            $readyStockByCampaign = [];
            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $campaign = $cartItem->campaign;

                if (! in_array($campaign->status, [CampaignStatus::Active, CampaignStatus::SoldOut], true)) {
                    throw new DomainException('Kampanye dalam keranjang sudah tidak tersedia.');
                }

                $campaignSize = $this->findCampaignSize($campaign, $cartItem->size);

                if ($campaign->status === CampaignStatus::SoldOut && $campaignSize->stock < 1) {
                    throw new DomainException('Kampanye dalam keranjang sudah tidak tersedia.');
                }

                $unitPrice = $this->priceFor($campaign, $campaignSize);
                $totalPrice = $unitPrice * $cartItem->qty;

                $requestedByCampaign[$campaign->id] = ($requestedByCampaign[$campaign->id] ?? 0) + $cartItem->qty;
                $readyStockByCampaign[$campaign->id][$campaignSize->size->value] = $campaignSize->stock;
                $subtotal += $totalPrice;
                $lineItems[] = [
                    'campaign' => $campaign,
                    'size' => $campaignSize->size,
                    'qty' => $cartItem->qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ];
            }

            foreach ($requestedByCampaign as $campaignId => $requestedQty) {
                $campaign = $cartItems->firstWhere('campaign_id', $campaignId)->campaign;

                $readyStock = array_sum($readyStockByCampaign[$campaignId] ?? []);

                if ($readyStock + $this->quotaService->availableSlots($campaign) < $requestedQty) {
                    throw new DomainException('Stok dan kuota kampanye tidak mencukupi.');
                }
            }

            $discountAmount = 0.0;

            if ($coupon !== null) {
                $coupon = $this->couponService->resolve($coupon->code, $user, (float) $subtotal);
                $coupon = $this->couponService->consume($coupon);
                $discountAmount = $this->couponService->discountFor($coupon, (float) $subtotal);
            }

            $shippingCost = $shipping === [] ? 0 : (float) ($shipping['shipping_cost'] ?? 0);
            $totalAmount = $subtotal - $discountAmount + $shippingCost;
            $dpAmount = $paymentMode === PaymentMode::Dp
                ? max(1, (int) round($totalAmount * 0.5))
                : null;
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::MenungguPembayaran,
                'payment_mode' => $paymentMode,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'discount_amount' => $discountAmount,
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
                'dp_amount' => $dpAmount,
                'payment_due_at' => null,
                'address_id' => $shipping['address_id'] ?? null,
                'shipping_cost' => $shipping === [] ? null : $shippingCost,
                'courier_code' => $shipping['courier_code'] ?? null,
                'courier_service' => $shipping['courier_service'] ?? null,
            ]);

            foreach ($lineItems as $lineItem) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'campaign_id' => $lineItem['campaign']->id,
                    'size' => $lineItem['size'],
                    'qty' => $lineItem['qty'],
                    'unit_price' => $lineItem['unit_price'],
                    'total_price' => $lineItem['total_price'],
                ]);
            }

            $this->recordStatusHistory($order, OrderStatus::MenungguPembayaran, 'Order dibuat');
            CartItem::query()->where('user_id', $user->id)->delete();

            $this->queueEmailAfterCommit($order->id, 'order_created');

            return $order->load('items');
        });
    }

    public function recordStatusHistory(Order $order, OrderStatus $status, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $note): Order {
            $this->assertValidTransition($order->status, $status);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'status' => $status,
                'note' => $note,
            ]);
            $order->status = $status;
            $order->save();

            return $order;
        });
    }

    public function markDpPaid(Order $order, Payment $payment, ?string $note = 'DP dibayar'): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $payment, $note): Order {
            $this->assertPaymentBelongsToOrder($order, $payment);

            if ($order->status !== OrderStatus::MenungguPembayaran
                || ! in_array($payment->payment_type, [PaymentType::Dp, PaymentType::Full], true)) {
                throw new DomainException('Transisi pembayaran DP tidak valid.');
            }

            $this->reserveOrderQuota($order);
            $payment->payment_status = PaymentStatus::Sukses;
            $payment->paid_at = now();
            $payment->save();
            $order->payment_due_at = now()->addHours(48);
            $order->save();

            return $this->recordStatusHistory($order, OrderStatus::DpDibayar, $note);
        });

        $this->queueEmailAfterCommit($updatedOrder->id, 'dp_received', [
            'paid_amount' => (float) $payment->amount,
            'payment_label' => 'DP',
        ]);

        return $updatedOrder;
    }

    public function markPaid(Order $order, Payment $payment): Order
    {
        return $this->markLunas($order, $payment);
    }

    public function markLunas(Order $order, Payment $payment, ?string $note = 'Pembayaran lunas'): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $payment, $note): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertPaymentBelongsToOrder($lockedOrder, $payment);

            if ($payment->payment_type === PaymentType::Full) {
                if ($lockedOrder->payment_mode !== PaymentMode::Lunas || $lockedOrder->status !== OrderStatus::MenungguPembayaran) {
                    throw new DomainException('Transisi pembayaran lunas tidak valid.');
                }

                $this->reserveOrderQuota($lockedOrder);
            } elseif ($payment->payment_type === PaymentType::Pelunasan) {
                if ($lockedOrder->payment_mode !== PaymentMode::Dp || $lockedOrder->status !== OrderStatus::SiapKirim) {
                    throw new DomainException('Transisi pelunasan tidak valid.');
                }
            } else {
                throw new DomainException('Jenis pembayaran tidak dapat melunasi order.');
            }

            $payment->payment_status = PaymentStatus::Sukses;
            $payment->paid_at = now();
            $payment->save();
            $lockedOrder->payment_due_at = null;
            $lockedOrder->save();

            return $this->recordStatusHistory($lockedOrder, OrderStatus::Lunas, $note);
        });

        $this->queueEmailAfterCommit($updatedOrder->id, 'dp_received', [
            'paid_amount' => (float) $payment->amount,
            'payment_label' => $payment->payment_type === PaymentType::Full ? 'Pembayaran penuh' : 'Pelunasan',
        ]);

        return $updatedOrder;
    }

    public function complete(Order $order): Order
    {
        if ($order->status === OrderStatus::Lunas) {
            return $order->fresh(['items', 'payments']);
        }

        $payment = $order->payments()->latest('id')->first();

        if ($payment === null) {
            throw new DomainException('Pembayaran order tidak ditemukan.');
        }

        return $this->markLunas($order, $payment);
    }

    public function cancel(Order $order, OrderStatus $status, ?string $note = null): Order
    {
        $updatedOrder = DB::transaction(function () use ($order, $status, $note): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($status, [OrderStatus::DibatalkanBuyer, OrderStatus::DibatalkanSistem], true)) {
                throw new DomainException('Status pembatalan tidak valid.');
            }

            $allowed = self::TRANSITIONS[$lockedOrder->status->value] ?? [];

            if (! in_array($status->value, $allowed, true)) {
                throw new DomainException('Order tidak dapat dibatalkan pada status ini.');
            }

            if (in_array($lockedOrder->status, [
                OrderStatus::DpDibayar,
                OrderStatus::Lunas,
            ], true)) {
                $this->releaseOrderQuota($lockedOrder);
                $this->releaseReservedReadyStock($lockedOrder);
            }

            // Barang yang sudah masuk produksi tetap menempati slot kuota, sehingga
            // unitnya dikembalikan sebagai ready stock, bukan sebagai kuota baru.
            if (in_array($lockedOrder->status, [OrderStatus::Produksi, OrderStatus::SiapKirim], true)) {
                $this->releaseReadyStock($lockedOrder);
            }

            $updatedOrder = $this->recordStatusHistory($lockedOrder, $status, $note);
            $this->couponService->releaseUsage($lockedOrder);

            return $updatedOrder;
        });

        $this->queueEmailAfterCommit($updatedOrder->id, 'order_cancelled', [
            'cancel_reason' => $note,
        ]);

        return $updatedOrder;
    }

    public function transition(Order $order, OrderStatus $status, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $status, $note): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $lockedOrder->status->value;
            $allowed = self::TRANSITIONS[$from] ?? [];

            if (! in_array($status->value, $allowed, true)) {
                throw new DomainException("Transisi status tidak valid: {$from} ke {$status->value}");
            }

            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'status' => $status,
                'note' => $note,
            ]);
            if ($status === OrderStatus::SiapKirim && $lockedOrder->payment_due_at === null) {
                $lockedOrder->payment_due_at = now()->addHours(48);
            }
            $lockedOrder->status = $status;
            $lockedOrder->save();
        });

        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId, $status): void {
            $updatedOrder = Order::query()->with('user')->find($orderId);

            if ($updatedOrder !== null) {
                $this->notifyStatusChange($updatedOrder, $status);
            }
        });
    }

    public function markProduksi(Order $order, ?string $note = null): void
    {
        $this->transition($order, OrderStatus::Produksi, $note);
    }

    public function markSiapKirim(Order $order, ?string $note = null): void
    {
        $this->transition($order, OrderStatus::SiapKirim, $note);
    }

    public function markDikirim(Order $order, ?string $note = null): void
    {
        $this->transition($order, OrderStatus::Dikirim, $note);
    }

    public function markDelivered(Order $order, ?string $note = null): void
    {
        $this->transition($order, OrderStatus::Delivered, $note);
    }

    /**
     * Get the valid next statuses for the order's current status.
     *
     * @return array<int, string>
     */
    public function allowedTransitions(Order $order): array
    {
        return self::TRANSITIONS[$order->status->value] ?? [];
    }

    private function notifyStatusChange(Order $order, OrderStatus $to): void
    {
        $template = match ($to) {
            OrderStatus::SiapKirim => 'order_status_ready',
            OrderStatus::Dikirim => 'order_status_sent',
            OrderStatus::Delivered => 'order_status_delivered',
            default => null,
        };

        if ($template === null) {
            return;
        }

        $user = $order->getRelationValue('user');

        if (trim((string) ($user instanceof User ? $user->phone : null)) !== '') {
            SendWhatsAppMessage::dispatchForOrder($order, $template);

            if ($to === OrderStatus::SiapKirim && $order->payment_mode === PaymentMode::Dp) {
                SendWhatsAppMessage::dispatchForOrder($order, 'payment_link', [
                    'link' => route('orders.show', $order),
                ]);
            }
        }

        if (trim((string) ($user instanceof User ? $user->email : null)) !== '') {
            $emailTemplate = [
                'order_status_ready' => 'order_ready',
                'order_status_sent' => 'order_shipped',
                'order_status_delivered' => 'order_delivered',
            ][$template];
            SendEmailMessage::dispatchForOrder($order, $emailTemplate);

            if ($to === OrderStatus::SiapKirim && $order->payment_mode === PaymentMode::Dp) {
                SendEmailMessage::dispatchForOrder($order, 'payment_link', [
                    'link' => route('orders.show', $order),
                ]);
            }
        }
    }

    /**
     * Queue an email only after the order transaction has committed.
     *
     * @param  array<string, mixed>  $extra
     */
    private function queueEmailAfterCommit(int $orderId, string $templateKey, array $extra = []): void
    {
        DB::afterCommit(function () use ($orderId, $templateKey, $extra): void {
            try {
                $order = Order::query()->with('user')->find($orderId);

                $user = $order?->getRelationValue('user');

                if (! $user instanceof User || trim($user->email) === '') {
                    return;
                }

                SendEmailMessage::dispatchForOrder($order, $templateKey, $extra);
            } catch (Throwable) {
                // Email delivery must never break an order transition.
            }
        });
    }

    private function assertPaymentBelongsToOrder(Order $order, Payment $payment): void
    {
        if ($payment->order_id !== $order->id) {
            throw new DomainException('Pembayaran bukan milik order ini.');
        }
    }

    private function reserveOrderQuota(Order $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($item->campaign_id);
            $campaignSize = CampaignSize::query()
                ->where('campaign_id', $item->campaign_id)
                ->where('size', $item->size->value)
                ->lockForUpdate()
                ->firstOrFail();
            $readyStockQty = min($item->qty, max(0, $campaignSize->stock));
            $quotaQty = $item->qty - $readyStockQty;

            if ($this->quotaService->availableSlots($campaign) < $quotaQty) {
                throw new DomainException('Kuota kampanye tidak mencukupi.');
            }

            if ($readyStockQty > 0) {
                $campaignSize->decrement('stock', $readyStockQty);
            }

            if ($quotaQty > 0) {
                $campaign->increment('sold_count', $quotaQty);
            }

            $item->ready_stock_qty = $readyStockQty;
            $item->save();
        }
    }

    private function releaseOrderQuota(Order $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($item->campaign_id);
            $quotaQty = max(0, $item->qty - $item->ready_stock_qty);
            $campaign->decrement('sold_count', min($quotaQty, $campaign->sold_count));
        }
    }

    private function releaseReservedReadyStock(Order $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $campaignSize = CampaignSize::query()
                ->where('campaign_id', $item->campaign_id)
                ->where('size', $item->size->value)
                ->lockForUpdate()
                ->firstOrFail();

            $campaignSize->increment('stock', $item->ready_stock_qty);
        }
    }

    private function releaseReadyStock(Order $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $campaignSize = CampaignSize::query()
                ->where('campaign_id', $item->campaign_id)
                ->where('size', $item->size->value)
                ->lockForUpdate()
                ->firstOrFail();

            $campaignSize->increment('stock', $item->qty);
        }
    }

    private function assertValidTransition(OrderStatus $from, OrderStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        if (! in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new DomainException('Transisi status order tidak valid.');
        }
    }

    private function findCampaignSize(Campaign $campaign, Size $size): CampaignSize
    {
        $campaignSize = $campaign->relationLoaded('sizes')
            ? $campaign->sizes->first(fn (CampaignSize $item): bool => $item->size === $size)
            : CampaignSize::query()
                ->where('campaign_id', $campaign->id)
                ->where('size', $size->value)
                ->first();

        if ($campaignSize === null || ! $campaignSize->enabled) {
            throw new DomainException('Ukuran produk tidak tersedia.');
        }

        return $campaignSize;
    }

    private function priceFor(Campaign $campaign, CampaignSize $campaignSize): int
    {
        return $this->pricingService->priceFor(
            (float) $campaign->base_price,
            $campaignSize->size,
            $campaignSize->price_override === null ? null : (float) $campaignSize->price_override,
        );
    }
}
