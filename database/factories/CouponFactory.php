<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('COUPON-####'),
            'description' => null,
            'type' => CouponType::Fixed->value,
            'value' => 20000,
            'min_purchase' => null,
            'max_discount' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'per_user_limit' => 1,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
