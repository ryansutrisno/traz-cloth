<?php

namespace Database\Factories;

use App\Enums\WaMessageStatus;
use App\Models\Order;
use App\Models\WaMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaMessage>
 */
class WaMessageFactory extends Factory
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
            'phone' => '6281234567890',
            'template_key' => 'order_created',
            'payload' => [],
            'status' => WaMessageStatus::Queued->value,
            'error' => null,
            'sent_at' => null,
        ];
    }

    /**
     * Set the message status to queued.
     */
    public function queued(): static
    {
        return $this->state([
            'status' => WaMessageStatus::Queued->value,
            'error' => null,
            'sent_at' => null,
        ]);
    }

    /**
     * Set the message status to sent.
     */
    public function sent(): static
    {
        return $this->state([
            'status' => WaMessageStatus::Sent->value,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * Set the message status to failed.
     */
    public function failed(): static
    {
        return $this->state([
            'status' => WaMessageStatus::Failed->value,
            'error' => 'Pengiriman gagal.',
            'sent_at' => null,
        ]);
    }
}
