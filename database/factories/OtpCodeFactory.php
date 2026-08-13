<?php

namespace Database\Factories;

use App\Models\OtpCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpCode>
 */
class OtpCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => fake()->e164PhoneNumber(),
            'purpose' => 'registration',
            'code' => Hash::make(str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ];
    }
}
