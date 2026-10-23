<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignImage>
 */
class CampaignImageFactory extends Factory
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
            'path' => 'campaigns/'.fake()->uuid().'.png',
            'is_primary' => false,
            'sort_order' => 0,
        ];
    }
}
