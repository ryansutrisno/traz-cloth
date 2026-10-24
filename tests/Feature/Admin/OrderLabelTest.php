<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_printable_label_with_resi(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $order = $this->order(['resi_number' => 'JNE-TEST-123']);

        $this->get(route('admin.orders.label', $order))
            ->assertOk()
            ->assertSee('JNE-TEST-123');
    }

    public function test_label_requires_resi_number(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $order = $this->order();

        $this->get(route('admin.orders.label', $order))->assertNotFound();
    }

    public function test_guest_is_redirected_from_printable_label(): void
    {
        $order = $this->order(['resi_number' => 'JNE-GUEST-123']);

        $this->get(route('admin.orders.label', $order))
            ->assertRedirect(route('admin.login'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create();
        $campaign = Campaign::factory()->active()->create();
        $order = Order::factory()->for($user)->create(array_merge([
            'address_id' => $address->id,
            'courier_code' => 'jne',
            'courier_service' => 'reg',
        ], $attributes));

        OrderItem::factory()->for($order)->for($campaign)->create();

        return $order;
    }
}
