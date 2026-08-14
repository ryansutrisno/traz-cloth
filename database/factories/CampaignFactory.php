<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraphs(3, true),
            'image_url' => fake()->imageUrl(),
            'base_price' => fake()->numberBetween(80000, 200000),
            'cost_per_item' => fake()->optional()->numberBetween(40000, 80000),
            'quota' => fake()->numberBetween(50, 200),
            'sold_count' => 0,
            'status' => CampaignStatus::Active->value,
            'ready_stock_enabled' => true,
        ];
    }

    /**
     * Set the campaign to active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::Active->value,
        ]);
    }

    /**
     * Indicate that the campaign is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::Draft->value,
        ]);
    }

    /**
     * Indicate that the campaign is sold out.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CampaignStatus::SoldOut->value,
            'sold_count' => $attributes['quota'],
        ]);
    }
}
