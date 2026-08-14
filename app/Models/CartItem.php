<?php

namespace App\Models;

use App\Enums\Size;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $campaign_id
 * @property Size $size
 * @property int $qty
 */
#[Fillable(['user_id', 'campaign_id', 'size', 'qty'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * Get the user who owns the cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the campaign in the cart item.
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
        ];
    }
}
