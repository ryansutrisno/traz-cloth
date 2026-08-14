<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'order_number' => fake()->unique()->bothify('TRAZ-########-????'),
            'status' => OrderStatus::MenungguPembayaran->value,
            'payment_mode' => PaymentMode::Dp->value,
            'subtotal' => 100000,
            'total_amount' => 100000,
            'dp_amount' => 50000,
            'payment_due_at' => null,
            'buyer_notes' => null,
        ];
    }

    /**
     * Set the order status.
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(['status' => $status->value]);
    }

    /**
     * Set the order to waiting for payment.
     */
    public function menungguPembayaran(): static
    {
        return $this->withStatus(OrderStatus::MenungguPembayaran);
    }

    /**
     * Set the order to DP paid.
     */
    public function dpDibayar(): static
    {
        return $this->withStatus(OrderStatus::DpDibayar);
    }

    /**
     * Set the order to production.
     */
    public function produksi(): static
    {
        return $this->withStatus(OrderStatus::Produksi);
    }

    /**
     * Set the order to ready to ship.
     */
    public function siapKirim(): static
    {
        return $this->withStatus(OrderStatus::SiapKirim);
    }

    /**
     * Set the order to fully paid.
     */
    public function lunas(): static
    {
        return $this->withStatus(OrderStatus::Lunas);
    }

    /**
     * Set the order to shipped.
     */
    public function dikirim(): static
    {
        return $this->withStatus(OrderStatus::Dikirim);
    }

    /**
     * Set the order to delivered.
     */
    public function delivered(): static
    {
        return $this->withStatus(OrderStatus::Delivered);
    }

    /**
     * Set the order to buyer-cancelled.
     */
    public function dibatalkanBuyer(): static
    {
        return $this->withStatus(OrderStatus::DibatalkanBuyer);
    }

    /**
     * Set the order to system-cancelled.
     */
    public function dibatalkanSistem(): static
    {
        return $this->withStatus(OrderStatus::DibatalkanSistem);
    }
}
