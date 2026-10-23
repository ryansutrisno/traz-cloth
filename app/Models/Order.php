<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $order_number
 * @property OrderStatus $status
 * @property PaymentMode $payment_mode
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property string $discount_amount
 * @property string $subtotal
 * @property string $total_amount
 * @property string|null $dp_amount
 * @property Carbon|null $payment_due_at
 * @property string|null $buyer_notes
 * @property int|null $address_id
 * @property string|null $shipping_cost
 * @property string|null $courier_code
 * @property string|null $courier_service
 * @property string|null $resi_number
 * @property string|null $biteship_order_id
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 */
#[Fillable(['user_id', 'order_number', 'status', 'payment_mode', 'coupon_id', 'coupon_code', 'discount_amount', 'subtotal', 'total_amount', 'dp_amount', 'payment_due_at', 'buyer_notes', 'address_id', 'shipping_cost', 'courier_code', 'courier_service', 'resi_number', 'biteship_order_id', 'shipped_at', 'delivered_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the user who placed the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shipping address for the order.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the items belonging to the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the status history entries for the order.
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Get the payments recorded for the order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_mode' => PaymentMode::class,
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'payment_due_at' => 'datetime',
            'shipping_cost' => 'decimal:2',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
