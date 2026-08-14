<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        ],
        'dikirim' => [
            'delivered',
        ],
        'delivered' => [],
    ];

    public function __construct(
        private PricingService $pricingService,
        private QuotaService $quotaService,
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

    public function createFromCart(User $user, PaymentMode $paymentMode, array $shipping = []): Order
    {
        return DB::transaction(function () use ($user, $paymentMode, $shipping): Order {
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
            $subtotal = 0;

            foreach ($cartItems as $cartItem) {
                $campaign = $cartItem->campaign;

                if ($campaign->status !== CampaignStatus::Active) {
                    throw new DomainException('Kampanye dalam keranjang sudah tidak tersedia.');
                }

                $campaignSize = $this->findCampaignSize($campaign, $cartItem->size);
                $unitPrice = $this->priceFor($campaign, $campaignSize);
                $totalPrice = $unitPrice * $cartItem->qty;

                $requestedByCampaign[$campaign->id] = ($requestedByCampaign[$campaign->id] ?? 0) + $cartItem->qty;
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

                if ($this->quotaService->availableSlots($campaign) < $requestedQty) {
                    throw new DomainException('Kuota kampanye tidak mencukupi.');
                }
            }

            $shippingCost = $shipping === [] ? 0 : (float) ($shipping['shipping_cost'] ?? 0);
            $totalAmount = $subtotal + $shippingCost;
            $dpAmount = $paymentMode === PaymentMode::Dp
                ? max(1, (int) round($totalAmount * 0.5))
                : null;
            $order = Order::query()->create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => OrderStatus::MenungguPembayaran,
                'payment_mode' => $paymentMode,
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
        return DB::transaction(function () use ($order, $payment, $note): Order {
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
    }

    public function markPaid(Order $order, Payment $payment): Order
    {
        return $this->markLunas($order, $payment);
    }

    public function markLunas(Order $order, Payment $payment, ?string $note = 'Pembayaran lunas'): Order
    {
        return DB::transaction(function () use ($order, $payment, $note): Order {
            $this->assertPaymentBelongsToOrder($order, $payment);

            if ($payment->payment_type === PaymentType::Full) {
                if ($order->payment_mode !== PaymentMode::Lunas || $order->status !== OrderStatus::MenungguPembayaran) {
                    throw new DomainException('Transisi pembayaran lunas tidak valid.');
                }

                $this->reserveOrderQuota($order);
            } elseif ($payment->payment_type === PaymentType::Pelunasan) {
                if ($order->payment_mode !== PaymentMode::Dp || $order->status !== OrderStatus::DpDibayar) {
                    throw new DomainException('Transisi pelunasan tidak valid.');
                }
            } else {
                throw new DomainException('Jenis pembayaran tidak dapat melunasi order.');
            }

            $payment->payment_status = PaymentStatus::Sukses;
            $payment->paid_at = now();
            $payment->save();
            $order->payment_due_at = null;
            $order->save();

            return $this->recordStatusHistory($order, OrderStatus::Lunas, $note);
        });
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
        return DB::transaction(function () use ($order, $status, $note): Order {
            if (! in_array($status, [OrderStatus::DibatalkanBuyer, OrderStatus::DibatalkanSistem], true)) {
                throw new DomainException('Status pembatalan tidak valid.');
            }

            if (! in_array($order->status, [OrderStatus::MenungguPembayaran, OrderStatus::DpDibayar], true)) {
                throw new DomainException('Order tidak dapat dibatalkan pada status ini.');
            }

            if ($order->status === OrderStatus::DpDibayar) {
                $this->releaseOrderQuota($order);
            }

            return $this->recordStatusHistory($order, $status, $note);
        });
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
            $lockedOrder->status = $status;
            $lockedOrder->save();
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

            if ($this->quotaService->availableSlots($campaign) < $item->qty) {
                throw new DomainException('Kuota kampanye tidak mencukupi.');
            }

            $campaign->increment('sold_count', $item->qty);
        }
    }

    private function releaseOrderQuota(Order $order): void
    {
        foreach ($order->items()->lockForUpdate()->get() as $item) {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($item->campaign_id);
            $campaign->decrement('sold_count', min($item->qty, $campaign->sold_count));
        }
    }

    private function assertValidTransition(OrderStatus $from, OrderStatus $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = match ($from) {
            OrderStatus::MenungguPembayaran => [
                OrderStatus::DpDibayar,
                OrderStatus::Lunas,
                OrderStatus::DibatalkanBuyer,
                OrderStatus::DibatalkanSistem,
            ],
            OrderStatus::DpDibayar => [
                OrderStatus::Produksi,
                OrderStatus::Lunas,
                OrderStatus::DibatalkanBuyer,
                OrderStatus::DibatalkanSistem,
            ],
            OrderStatus::Produksi => [OrderStatus::SiapKirim],
            OrderStatus::SiapKirim => [OrderStatus::Dikirim],
            OrderStatus::Dikirim => [OrderStatus::Delivered],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
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
