<?php

namespace Database\Factories;

use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignSize>
 */
class CampaignSizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'size' => fake()->randomElement(Size::cases())->value,
            'price_override' => null,
            'stock' => 0,
            'enabled' => true,
        ];
    }

    /**
     * Set the size to S.
     */
    public function s(): static
    {
        return $this->state(['size' => Size::S->value]);
    }

    /**
     * Set the size to M.
     */
    public function m(): static
    {
        return $this->state(['size' => Size::M->value]);
    }

    /**
     * Set the size to L.
     */
    public function l(): static
    {
        return $this->state(['size' => Size::L->value]);
    }

    /**
     * Set the size to XL.
     */
    public function xl(): static
    {
        return $this->state(['size' => Size::XL->value]);
    }

    /**
     * Set the size to XXL.
     */
    public function xxl(): static
    {
        return $this->state(['size' => Size::XXL->value]);
    }
}
