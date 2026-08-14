<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookCourierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_book_courier_for_lunas_order(): void
    {
        $this->login(AdminRole::Admin);
        $order = $this->order(OrderStatus::Lunas);

        $this->post(route('admin.orders.book-courier', $order), [
            'courier_code' => 'jne',
            'courier_service' => 'reg',
            'shipping_cost' => 15000,
        ])->assertRedirect(route('admin.orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Dikirim, $order->status);
        $this->assertSame('DEV-'.$order->order_number, $order->resi_number);
        $this->assertSame('dev-'.$order->order_number, $order->biteship_order_id);
        $this->assertNotNull($order->shipped_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => OrderStatus::Dikirim->value,
        ]);
    }

    public function test_courier_booking_requires_lunas_status(): void
    {
        $this->login(AdminRole::Operator);
        $order = $this->order(OrderStatus::DpDibayar);

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.book-courier', $order), [
                'courier_code' => 'jne',
                'courier_service' => 'reg',
                'shipping_cost' => 15000,
            ])
            ->assertSessionHasErrors('courier');
    }

    public function test_courier_rates_require_lunas_status(): void
    {
        $this->login(AdminRole::Operator);
        $order = $this->order(OrderStatus::DpDibayar);

        $this->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.courier-rates', $order))
            ->assertSessionHasErrors('courier');
    }

    private function login(AdminRole $role): void
    {
        $this->actingAs(Admin::factory()->create(['role' => $role]), 'admin');
    }

    private function order(OrderStatus $status): Order
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->default()->create();
        $campaign = Campaign::factory()->active()->create();
        $order = Order::factory()->for($user)->create([
            'status' => $status,
            'address_id' => $address->id,
        ]);
        OrderItem::factory()->for($order)->for($campaign)->create();

        return $order;
    }
}
