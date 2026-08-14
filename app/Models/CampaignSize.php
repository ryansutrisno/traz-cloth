<?php

namespace App\Models;

use App\Enums\Size;
use Database\Factories\CampaignSizeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $campaign_id
 * @property Size $size
 * @property string|null $price_override
 * @property int $stock
 * @property bool $enabled
 */
#[Fillable(['campaign_id', 'size', 'price_override', 'stock', 'enabled'])]
class CampaignSize extends Model
{
    /** @use HasFactory<CampaignSizeFactory> */
    use HasFactory;

    /**
     * Get the campaign that owns the size configuration.
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
            'price_override' => 'decimal:2',
            'enabled' => 'boolean',
        ];
    }
}
