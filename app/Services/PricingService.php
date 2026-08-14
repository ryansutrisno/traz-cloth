<?php

namespace App\Services;

use App\Enums\Size;

class PricingService
{
    public function priceFor(int|float $basePrice, Size $size, ?float $priceOverride = null): int
    {
        if ($priceOverride !== null) {
            return (int) round($priceOverride);
        }

        $breakdown = $this->priceBreakdown($basePrice, $size);

        return $breakdown['final'];
    }

    /**
     * Calculate the final price and its component values for a campaign size.
     *
     * @return array{final: int, base: float, adjustment: int, surcharge_large: int, press_cost: int, override: float|null}
     */
    public function priceBreakdown(int|float $basePrice, Size $size, ?float $priceOverride = null): array
    {
        $base = (float) $basePrice;
        $adjustment = (int) config('pricing.adjustments.'.$size->value, 0);
        $surchargeLarge = in_array(
            $size->value,
            config('pricing.surcharge_large_sizes', []),
            true,
        ) ? (int) config('pricing.surcharge_large', 0) : 0;
        $pressCost = (int) config('pricing.press_cost', 0);
        $final = $priceOverride !== null
            ? (int) round($priceOverride)
            : (int) round($base + $adjustment + $surchargeLarge + $pressCost);

        return [
            'final' => $final,
            'base' => $base,
            'adjustment' => $adjustment,
            'surcharge_large' => $surchargeLarge,
            'press_cost' => $pressCost,
            'override' => $priceOverride,
        ];
    }
}
