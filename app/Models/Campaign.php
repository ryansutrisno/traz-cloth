<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\CampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $image_url
 * @property string $base_price
 * @property string|null $cost_per_item
 * @property int $quota
 * @property int $sold_count
 * @property CampaignStatus $status
 * @property bool $ready_stock_enabled
 */
#[Fillable(['slug', 'title', 'description', 'image_url', 'base_price', 'cost_per_item', 'quota', 'sold_count', 'status', 'ready_stock_enabled'])]
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
            'cost_per_item' => 'decimal:2',
            'status' => CampaignStatus::class,
            'ready_stock_enabled' => 'boolean',
        ];
    }

    /**
     * Get the public URL for the campaign image while preserving legacy URLs.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || Str::startsWith($value, ['http://', 'https://'])) {
                    return $value;
                }

                return Storage::disk('public')->url($value);
            },
        );
    }

    /**
     * Get the sizes configured for the campaign.
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(CampaignSize::class);
    }

    /**
     * Get the campaign gallery images in display order.
     */
    public function images(): HasMany
    {
        return $this->hasMany(CampaignImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Get the campaign's primary gallery image.
     */
    public function primaryImage(): ?CampaignImage
    {
        if ($this->relationLoaded('images')) {
            return $this->images->first();
        }

        return $this->images()
            ->where('is_primary', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }
}
