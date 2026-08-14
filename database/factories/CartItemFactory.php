<?php

namespace Database\Factories;

use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'campaign_id' => Campaign::factory(),
            'size' => fake()->randomElement(Size::cases())->value,
            'qty' => 1,
        ];
    }
}
