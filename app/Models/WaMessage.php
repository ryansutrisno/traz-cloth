<?php

namespace App\Models;

use App\Enums\WaMessageStatus;
use Database\Factories\WaMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $order_id
 * @property string $phone
 * @property string $template_key
 * @property array<string, mixed> $payload
 * @property WaMessageStatus $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
#[Fillable(['order_id', 'phone', 'template_key', 'payload', 'status', 'error', 'sent_at'])]
class WaMessage extends Model
{
    /** @use HasFactory<WaMessageFactory> */
    use HasFactory;

    /**
     * Get the order associated with the WhatsApp message.
     */
    /** @return BelongsTo<Order, $this> */
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
            'payload' => 'array',
            'status' => WaMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
