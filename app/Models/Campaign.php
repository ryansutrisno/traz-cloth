<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $image_url
 * @property string $base_price
 * @property int $quota
 * @property int $sold_count
 * @property CampaignStatus $status
 * @property bool $ready_stock_enabled
 */
#[Fillable(['slug', 'title', 'description', 'image_url', 'base_price', 'quota', 'sold_count', 'status', 'ready_stock_enabled'])]
class Campaign extends Model
{
    /** @use HasFactory<CampaignFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'status' => CampaignStatus::class,
            'ready_stock_enabled' => 'boolean',
        ];
    }

    /**
     * Get the sizes configured for the campaign.
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(CampaignSize::class);
    }
}
