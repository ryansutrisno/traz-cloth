<?php

namespace App\Services;

use App\Models\Campaign;

class QuotaService
{
    public function availableSlots(Campaign $campaign): int
    {
        return max(0, $campaign->quota - $campaign->sold_count);
    }

    public function isSoldOut(Campaign $campaign): bool
    {
        if ($this->availableSlots($campaign) > 0) {
            return false;
        }

        $readyStock = $campaign->relationLoaded('sizes')
            ? $campaign->sizes->sum('stock')
            : $campaign->sizes()->where('stock', '>', 0)->sum('stock');

        return $readyStock <= 0;
    }

    public function progressPercent(Campaign $campaign): int
    {
        if ($campaign->quota <= 0) {
            return 0;
        }

        return (int) min(100, max(0, ($campaign->sold_count / $campaign->quota) * 100));
    }
}
