<?php

namespace Tests\Feature\Admin;

use App\Enums\CouponType;
use App\Models\Admin;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CouponAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    public function test_admin_can_list_coupons(): void
    {
        Coupon::factory()->create();

        $this->get(route('admin.coupons.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/coupons/index', false)->has('coupons.data', 1));
    }

    public function test_admin_can_create_and_update_coupon(): void
    {
        $response = $this->post(route('admin.coupons.store'), [
            'code' => ' hemat10 ',
            'type' => CouponType::Percentage->value,
            'value' => 10,
            'is_active' => true,
        ]);
        $coupon = Coupon::query()->firstOrFail();

        $response->assertRedirect(route('admin.coupons.edit', $coupon));
        $this->assertSame('HEMAT10', $coupon->code);

        $this->put(route('admin.coupons.update', $coupon), [
            'code' => 'hemat20',
            'type' => CouponType::Fixed->value,
            'value' => 20000,
            'is_active' => false,
        ])->assertRedirect(route('admin.coupons.edit', $coupon));

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'HEMAT20', 'is_active' => false]);
    }

    public function test_updating_coupon_without_active_checkbox_deactivates_it(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => true]);

        $this->put(route('admin.coupons.update', $coupon), [
            'code' => $coupon->code,
            'type' => CouponType::Fixed->value,
            'value' => 15000,
        ])->assertRedirect(route('admin.coupons.edit', $coupon));

        $this->assertFalse($coupon->refresh()->is_active);
    }

    public function test_admin_can_delete_unused_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->delete(route('admin.coupons.destroy', $coupon))->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_delete_used_coupon(): void
    {
        $coupon = Coupon::factory()->create(['used_count' => 1]);

        $this->from(route('admin.coupons.index'))->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'))->assertSessionHasErrors('coupon');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    public function test_coupon_code_must_be_unique(): void
    {
        Coupon::factory()->create(['code' => 'DUPLIKAT']);

        $this->post(route('admin.coupons.store'), [
            'code' => 'duplikat',
            'type' => CouponType::Fixed->value,
            'value' => 10000,
        ])->assertSessionHasErrors('code');
    }
}
