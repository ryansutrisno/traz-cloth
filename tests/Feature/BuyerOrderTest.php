<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_buyer_order_index_only_shows_own_orders(): void
    {
        $user = User::factory()->create();
        $ownOrder = Order::factory()->for($user)->create();
        Order::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/index', false)
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $ownOrder->order_number)
                ->has('orders.pagination'),
            );
    }

    public function test_buyer_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create();
        $order = Order::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_buyer_can_pay_remaining_for_ready_order_in_dev_mode(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::SiapKirim,
            'payment_mode' => PaymentMode::Dp,
            'total_amount' => 100000,
            'dp_amount' => 50000,
        ]);

        $this->actingAs($user)
            ->post(route('orders.pay-remaining', $order))
            ->assertRedirect(route('checkout.dev', $order));

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_type' => PaymentType::Pelunasan->value,
            'payment_status' => PaymentStatus::Menunggu->value,
            'midtrans_order_id' => $order->order_number.'-2',
        ]);
    }

    public function test_buyer_cannot_pay_remaining_before_ready_to_ship(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::DpDibayar,
            'payment_mode' => PaymentMode::Dp,
        ]);

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay-remaining', $order))
            ->assertSessionHasErrors('order');
    }

    public function test_order_show_exposes_remaining_payment_state(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::SiapKirim,
            'payment_mode' => PaymentMode::Dp,
            'total_amount' => 100000,
            'dp_amount' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/show', false)
                ->where('can_pay_remaining', true)
                ->where('remaining_amount', '50000.00'),
            );

        $this->assertDatabaseMissing('payments', ['order_id' => $order->id]);
    }

    public function test_order_show_exposes_shipping_information(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'courier_code' => 'jne',
            'courier_service' => 'REG',
            'resi_number' => 'JNE123456789',
            'shipped_at' => '2026-08-20 10:00:00',
            'delivered_at' => '2026-08-22 15:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/show', false)
                ->where('order.courier_code', 'jne')
                ->where('order.courier_service', 'REG')
                ->where('order.resi_number', 'JNE123456789')
                ->where('order.shipped_at', '2026-08-20T10:00:00.000000Z')
                ->where('order.delivered_at', '2026-08-22T15:30:00.000000Z'),
            );
    }

    public function test_buyer_can_cancel_own_unpaid_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->menungguPembayaran()->create();

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
    }

    public function test_buyer_cannot_cancel_another_users_order(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->post(route('orders.cancel', $order))
            ->assertForbidden();
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->delivered()->create();

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.cancel', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
    }

    public function test_cancelling_dp_paid_order_returns_campaign_quota(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->active()->create([
            'quota' => 10,
            'sold_count' => 1,
        ]);
        CampaignSize::factory()->for($campaign)->s()->create(['stock' => 0]);
        $order = Order::factory()->for($user)->dpDibayar()->create();
        OrderItem::factory()->for($order)->for($campaign)->create([
            'size' => 'S',
            'qty' => 1,
            'ready_stock_qty' => 0,
        ]);

        $this->actingAs($user)
            ->post(route('orders.cancel', $order))
            ->assertRedirect();

        $this->assertSame(0, $campaign->refresh()->sold_count);
        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
    }
}
