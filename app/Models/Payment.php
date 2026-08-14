<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property PaymentType $payment_type
 * @property PaymentStatus $payment_status
 * @property string $amount
 * @property string|null $midtrans_order_id
 * @property string|null $midtrans_transaction_id
 * @property Carbon|null $paid_at
 */
#[Fillable(['order_id', 'payment_type', 'payment_status', 'amount', 'midtrans_order_id', 'midtrans_transaction_id', 'paid_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the order associated with the payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'payment_status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
