<?php

namespace App\Models;

use App\Enums\Size;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $campaign_id
 * @property Size $size
 * @property int $qty
 * @property string $unit_price
 * @property string $total_price
 */
#[Fillable(['order_id', 'campaign_id', 'size', 'qty', 'unit_price', 'total_price'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * Get the order containing the item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the campaign purchased in the item.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => Size::class,
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }
}
