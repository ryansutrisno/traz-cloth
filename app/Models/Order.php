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
 * @property string $subtotal
 * @property string $total_amount
 * @property string|null $dp_amount
 * @property Carbon|null $payment_due_at
 * @property string|null $buyer_notes
 */
#[Fillable(['user_id', 'order_number', 'status', 'payment_mode', 'subtotal', 'total_amount', 'dp_amount', 'payment_due_at', 'buyer_notes'])]
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
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'dp_amount' => 'decimal:2',
            'payment_due_at' => 'datetime',
        ];
    }
}
