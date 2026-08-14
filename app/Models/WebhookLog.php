<?php

namespace App\Models;

use App\Enums\WebhookProvider;
use Database\Factories\WebhookLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property WebhookProvider $provider
 * @property string|null $event
 * @property string|null $event_id
 * @property array<string, mixed> $payload
 * @property bool $processed
 * @property string|null $error
 */
#[Fillable(['provider', 'event', 'event_id', 'payload', 'processed', 'error'])]
class WebhookLog extends Model
{
    /** @use HasFactory<WebhookLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'provider' => WebhookProvider::class,
            'processed' => 'boolean',
        ];
    }
}
