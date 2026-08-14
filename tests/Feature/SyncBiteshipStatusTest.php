<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncBiteshipStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_biteship_status_command_marks_delivered_order(): void
    {
        $order = Order::factory()->for(User::factory())->create([
            'status' => OrderStatus::Dikirim,
            'biteship_order_id' => 'dev-biteship-sync',
        ]);

        $this->artisan('biteship:sync-status')
            ->assertExitCode(0);

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertNotNull($order->delivered_at);
    }
}
