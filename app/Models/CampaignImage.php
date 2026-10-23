<?php

namespace App\Models;

use Database\Factories\CampaignImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['campaign_id', 'path', 'is_primary', 'sort_order'])]
class CampaignImage extends Model
{
    /** @use HasFactory<CampaignImageFactory> */
    use HasFactory;

    /**
     * Get the campaign that owns the image.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the public URL for the image while preserving legacy URLs.
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                if (Str::startsWith($value, ['http://', 'https://'])) {
                    return $value;
                }

                return Storage::disk('public')->url($value);
            },
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
