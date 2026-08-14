<?php

namespace Database\Factories;

use App\Enums\Size;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'campaign_id' => Campaign::factory(),
            'size' => fake()->randomElement(Size::cases())->value,
            'qty' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ];
    }
}
