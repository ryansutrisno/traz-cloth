<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'payment_type' => PaymentType::Dp->value,
            'payment_status' => PaymentStatus::Menunggu->value,
            'amount' => 50000,
            'midtrans_order_id' => null,
            'midtrans_transaction_id' => null,
            'paid_at' => null,
        ];
    }

    /**
     * Set the payment type to DP.
     */
    public function dp(): static
    {
        return $this->state(['payment_type' => PaymentType::Dp->value]);
    }

    /**
     * Set the payment type to full payment.
     */
    public function full(): static
    {
        return $this->state(['payment_type' => PaymentType::Full->value]);
    }

    /**
     * Set the payment type to settlement.
     */
    public function pelunasan(): static
    {
        return $this->state(['payment_type' => PaymentType::Pelunasan->value]);
    }

    /**
     * Set the payment status to successful.
     */
    public function sukses(): static
    {
        return $this->state([
            'payment_status' => PaymentStatus::Sukses->value,
            'paid_at' => now(),
        ]);
    }

    /**
     * Set the payment status to failed.
     */
    public function gagal(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Gagal->value]);
    }

    /**
     * Set the payment status to expired.
     */
    public function kedaluwarsa(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Kedaluwarsa->value]);
    }

    /**
     * Set the payment status to refunded.
     */
    public function refund(): static
    {
        return $this->state(['payment_status' => PaymentStatus::Refund->value]);
    }
}
