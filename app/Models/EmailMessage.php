<?php

namespace App\Models;

use App\Enums\EmailMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int|null $user_id
 * @property string $email
 * @property string $template_key
 * @property array<string, mixed> $payload
 * @property EmailMessageStatus $status
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
#[Fillable(['order_id', 'user_id', 'email', 'template_key', 'payload', 'status', 'error', 'sent_at'])]
class EmailMessage extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => EmailMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
