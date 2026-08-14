<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_view_and_filter_order_index(): void
    {
        $this->login();
        $order = $this->orderWithStatus(OrderStatus::MenungguPembayaran);

        $this->get(route('admin.orders.index', ['q' => $order->order_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index', false)
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $order->order_number)
                ->where('filters.q', $order->order_number)
                ->has('statuses', 9),
            );
    }

    public function test_admin_can_view_order_detail(): void
    {
        $this->login();
        $order = $this->orderWithStatus(OrderStatus::MenungguPembayaran);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/show', false)
                ->where('order.order_number', $order->order_number)
                ->has('order.items', 1)
                ->has('order.histories', 1),
            );
    }

    public function test_admin_can_transition_order_and_history_is_recorded(): void
    {
        $this->login();
        $order = $this->orderWithStatus(OrderStatus::SiapKirim);

        $this->post(route('admin.orders.transition', $order), [
            'status' => OrderStatus::Lunas->value,
            'note' => 'Pelunasan diverifikasi.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Lunas, $order->refresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Lunas->value,
            'note' => 'Pelunasan diverifikasi.',
        ]);
    }

    public function test_admin_cannot_make_invalid_transition(): void
    {
        $this->login();
        $order = $this->orderWithStatus(OrderStatus::Delivered);

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.transition', $order), [
                'status' => OrderStatus::Produksi->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
    }

    private function login(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    private function orderWithStatus(OrderStatus $status): Order
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->active()->create();
        $order = Order::factory()->for($user)->create([
            'status' => $status,
            'payment_mode' => PaymentMode::Dp,
        ]);
        OrderItem::factory()->for($order)->for($campaign)->create();
        OrderStatusHistory::factory()->for($order)->create(['status' => $status]);

        return $order;
    }
}
