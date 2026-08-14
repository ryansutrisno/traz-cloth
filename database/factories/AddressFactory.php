<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
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
            'label' => 'Rumah',
            'recipient_name' => fake()->name(),
            'phone' => '6281234567890',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Kebayoran Baru',
            'subdistrict' => 'Senayan',
            'postal_code' => '12190',
            'full_address' => fake()->address(),
            'is_default' => false,
        ];
    }

    /**
     * Set the address as the user's default address.
     */
    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
