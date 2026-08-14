<?php

namespace Database\Factories;

use App\Enums\WebhookProvider;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => WebhookProvider::Midtrans->value,
            'event' => 'payment_notification',
            'event_id' => fake()->uuid(),
            'payload' => [],
            'processed' => false,
            'error' => null,
        ];
    }
}
