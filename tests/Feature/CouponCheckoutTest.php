<?php

namespace Tests\Feature;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\Size;
use App\Models\Address;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_percentage_coupon_reduces_total_and_dp(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();
        $coupon = Coupon::factory()->create(['code' => 'HEMAT10', 'type' => CouponType::Percentage, 'value' => 10]);

        $this->apply($user, $coupon);
        $this->checkout($user, $address, PaymentMode::Dp);

        $order = Order::query()->firstOrFail();
        $this->assertSame(10000.0, (float) $order->discount_amount);
        $this->assertSame(105000.0, (float) $order->total_amount);
        $this->assertSame(52500.0, (float) $order->dp_amount);
        $this->assertSame('HEMAT10', $order->coupon_code);
        $this->assertSame(1, $coupon->refresh()->used_count);
    }

    public function test_fixed_coupon_reduces_total(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();
        $coupon = Coupon::factory()->create(['code' => 'POTONG20', 'value' => 20000]);

        $this->apply($user, $coupon);
        $this->checkout($user, $address, PaymentMode::Lunas);

        $order = Order::query()->firstOrFail();
        $this->assertSame(20000.0, (float) $order->discount_amount);
        $this->assertSame(95000.0, (float) $order->total_amount);
    }

    public function test_percentage_coupon_respects_max_discount(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 2);
        $address = Address::factory()->for($user)->default()->create();
        $coupon = Coupon::factory()->create([
            'code' => 'MAX10',
            'type' => CouponType::Percentage,
            'value' => 50,
            'max_discount' => 10000,
        ]);

        $this->apply($user, $coupon);
        $this->checkout($user, $address, PaymentMode::Lunas);

        $this->assertSame(10000.0, (float) Order::query()->firstOrFail()->discount_amount);
    }

    public function test_minimum_purchase_keeps_cart_and_does_not_consume_coupon(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $coupon = Coupon::factory()->create(['code' => 'MIN200', 'min_purchase' => 200000]);

        $this->actingAs($user)->from(route('checkout.index'))
            ->post(route('checkout.coupon.apply'), ['code' => $coupon->code])
            ->assertSessionHasErrors('coupon');

        $this->assertSame(0, $coupon->refresh()->used_count);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_inactive_expired_and_exhausted_coupons_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);

        foreach ([
            Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]),
            Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subMinute()]),
            Coupon::factory()->create(['code' => 'FULL', 'usage_limit' => 1, 'used_count' => 1]),
        ] as $coupon) {
            $this->actingAs($user)->from(route('checkout.index'))
                ->post(route('checkout.coupon.apply'), ['code' => $coupon->code])
                ->assertSessionHasErrors('coupon');
        }
    }

    public function test_per_user_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'ONCE', 'per_user_limit' => 1]);
        Order::factory()->for($user)->create(['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code]);
        $this->cartFor($user, 1);

        $this->actingAs($user)->from(route('checkout.index'))
            ->post(route('checkout.coupon.apply'), ['code' => $coupon->code])
            ->assertSessionHasErrors('coupon');
    }

    public function test_canceling_order_releases_coupon_usage(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();
        $coupon = Coupon::factory()->create(['code' => 'CANCEL']);
        $this->apply($user, $coupon);
        $this->checkout($user, $address, PaymentMode::Lunas);
        $order = Order::query()->firstOrFail();

        $this->actingAs($user)->post(route('orders.cancel', $order))->assertRedirect();

        $this->assertSame(0, $coupon->refresh()->used_count);
        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
    }

    public function test_coupon_does_not_reduce_shipping_cost(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();
        $coupon = Coupon::factory()->create(['code' => 'ONGKIR', 'value' => 20000]);
        $this->apply($user, $coupon);
        $this->checkout($user, $address, PaymentMode::Lunas, 15000);

        $order = Order::query()->firstOrFail();
        $this->assertSame(80000.0, (float) $order->total_amount - (float) $order->shipping_cost);
        $this->assertSame(80000.0, (float) $order->subtotal - (float) $order->discount_amount);
        $this->assertSame(95000.0, (float) $order->total_amount);
    }

    public function test_checkout_page_sends_coupon_props_from_session(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $coupon = Coupon::factory()->create([
            'code' => 'HEMAT10',
            'description' => 'Diskon sepuluh persen',
            'type' => CouponType::Percentage,
            'value' => 10,
        ]);

        $this->actingAs($user)
            ->withSession(['checkout_coupon' => $coupon->code])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/index', false)
                ->where('discount_amount', 10000)
                ->where('coupon.code', 'HEMAT10')
                ->where('coupon.type', 'percentage'),
            );
    }

    public function test_applying_coupon_stores_code_in_session_and_redirects_back(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $coupon = Coupon::factory()->create(['code' => 'HEMAT10']);

        $this->actingAs($user)
            ->from(route('checkout.index'))
            ->post(route('checkout.coupon.apply'), ['code' => 'hemat10'])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('checkout_coupon', $coupon->code);
    }

    public function test_removing_coupon_forgets_code_from_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['checkout_coupon' => 'HEMAT10'])
            ->delete(route('checkout.coupon.remove'))
            ->assertRedirect()
            ->assertSessionMissing('checkout_coupon');
    }

    private function apply(User $user, Coupon $coupon): void
    {
        $this->actingAs($user)->post(route('checkout.coupon.apply'), ['code' => strtolower($coupon->code)])
            ->assertSessionHas('checkout_coupon', $coupon->code);
    }

    private function checkout(User $user, Address $address, PaymentMode $mode, int $shipping = 15000): void
    {
        $this->actingAs($user)->post(route('checkout.store'), [
            'payment_mode' => $mode->value,
            'address_id' => $address->id,
            'courier_code' => 'jne',
            'courier_service' => 'reg',
            'shipping_cost' => $shipping,
        ])->assertRedirect();
    }

    private function cartFor(User $user, int $qty): void
    {
        $campaign = Campaign::factory()->active()->create(['base_price' => 100000, 'quota' => 20]);
        CampaignSize::factory()->for($campaign)->s()->create(['enabled' => true, 'stock' => 0]);
        CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => $qty,
        ]);
    }
}
