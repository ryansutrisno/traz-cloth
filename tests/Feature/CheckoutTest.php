<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\Size;
use App\Models\Address;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->get(route('checkout.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('checkout.index'));
    }

    public function test_user_can_checkout_with_dp(): void
    {
        $user = User::factory()->create();
        [$campaign, $cartItem] = $this->cartFor($user, 2);
        $address = Address::factory()->for($user)->default()->create();
        $expectedUnitPrice = app(PricingService::class)->priceFor((float) $campaign->base_price, Size::S);
        $subtotal = $expectedUnitPrice * $cartItem->qty;
        $total = $subtotal + 15000;

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            ...$this->checkoutPayload($address, PaymentMode::Dp),
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $response->assertRedirect(route('checkout.dev', $order));
        $this->assertMatchesRegularExpression('/^TRAZ-\d{8}-[A-Z0-9]{4}$/', $order->order_number);
        $this->assertSame(OrderStatus::MenungguPembayaran, $order->status);
        $this->assertSame(PaymentMode::Dp, $order->payment_mode);
        $this->assertSame((float) $subtotal, (float) $order->subtotal);
        $this->assertSame((float) $total, (float) $order->total_amount);
        $this->assertSame((float) round($total * 0.5), (float) $order->dp_amount);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($cartItem->qty, $order->items()->first()->qty);
        $this->assertSame($expectedUnitPrice, (int) $order->items()->first()->unit_price);
        $this->assertSame(PaymentType::Dp, $payment->payment_type);
        $this->assertSame(PaymentStatus::Menunggu, $payment->payment_status);
        $this->assertSame((string) $order->dp_amount, (string) $payment->amount);
        $this->assertSame($order->order_number, $payment->midtrans_order_id);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_user_can_checkout_with_full_payment(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();

        $response = $this->actingAs($user)->post(route('checkout.store'), [
            ...$this->checkoutPayload($address, PaymentMode::Lunas),
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $response->assertRedirect(route('checkout.dev', $order));
        $this->assertSame(PaymentMode::Lunas, $order->payment_mode);
        $this->assertNull($order->dp_amount);
        $this->assertSame(PaymentType::Full, $payment->payment_type);
        $this->assertSame((float) $order->total_amount, (float) $payment->amount);
    }

    public function test_checkout_rejects_insufficient_quota_and_keeps_cart(): void
    {
        $user = User::factory()->create();
        [$campaign] = $this->cartFor($user, 2, 1);
        $address = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->checkoutPayload($address, PaymentMode::Dp))
            ->assertSessionHasErrors('checkout');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'qty' => 2,
        ]);
    }

    public function test_dev_pay_marks_dp_paid_and_reserves_campaign_quota(): void
    {
        $user = User::factory()->create();
        [$campaign] = $this->cartFor($user, 2);
        $order = $this->createOrder($user, PaymentMode::Dp);

        $this->actingAs($user)
            ->post(route('checkout.dev.pay', $order))
            ->assertRedirect(route('checkout.success', $order));

        $order->refresh();
        $payment = $order->payments()->firstOrFail();
        $campaign->refresh();

        $this->assertSame(OrderStatus::DpDibayar, $order->status);
        $this->assertSame(2, $campaign->sold_count);
        $this->assertSame(PaymentStatus::Sukses, $payment->payment_status);
        $this->assertSame('dev-'.$order->order_number, $payment->midtrans_transaction_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($order->payment_due_at);
        $this->assertTrue($order->payment_due_at->between(now()->addHours(47), now()->addHours(49)));
    }

    public function test_dev_pay_marks_full_payment_as_paid(): void
    {
        $user = User::factory()->create();
        [$campaign] = $this->cartFor($user, 1);
        $order = $this->createOrder($user, PaymentMode::Lunas);

        $this->actingAs($user)
            ->post(route('checkout.dev.pay', $order))
            ->assertRedirect(route('checkout.success', $order));

        $order->refresh();
        $campaign->refresh();

        $this->assertSame(OrderStatus::Lunas, $order->status);
        $this->assertSame(1, $campaign->sold_count);
        $this->assertNull($order->payment_due_at);
        $this->assertSame(PaymentStatus::Sukses, $order->payments()->firstOrFail()->payment_status);
    }

    public function test_dev_route_is_unavailable_when_midtrans_is_configured(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        config()->set('midtrans.server_key', 'SB-xxx');

        $this->actingAs($user)
            ->get(route('checkout.dev', $order))
            ->assertNotFound();
    }

    public function test_user_can_view_own_success_page_but_not_another_users_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('checkout.success', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/success', false)
                ->where('order.order_number', $order->order_number),
            );

        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->get(route('checkout.success', $order))
            ->assertForbidden();
    }

    public function test_canceled_checkout_redirects_to_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('checkout.canceled'))
            ->assertRedirect(route('cart.index'));
    }

    public function test_checkout_requires_an_address(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);

        $this->actingAs($user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), [
                'payment_mode' => PaymentMode::Dp->value,
                'courier_code' => 'jne',
                'courier_service' => 'reg',
                'shipping_cost' => 15000,
            ])
            ->assertSessionHasErrors('address_id');
    }

    public function test_checkout_rates_returns_dev_rate(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->post(route('checkout.rates'), ['address_id' => $address->id])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/index', false)
                ->has('courier_rates', 1)
                ->where('courier_rates.0.courier_code', 'jne')
                ->where('selected_address_id', $address->id),
            );
    }

    public function test_checkout_rejects_invalid_courier_rate(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user, 1);
        $address = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->checkoutPayload($address, PaymentMode::Dp, 'sicepat', 'best'))
            ->assertSessionHasErrors('courier');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @return array{0: Campaign, 1: CartItem}
     */
    private function cartFor(User $user, int $qty, int $quota = 20): array
    {
        $campaign = Campaign::factory()->active()->create([
            'base_price' => 100000,
            'quota' => $quota,
            'sold_count' => 0,
        ]);
        CampaignSize::factory()->for($campaign)->s()->create([
            'enabled' => true,
            'price_override' => null,
        ]);
        $cartItem = CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => $qty,
        ]);

        return [$campaign, $cartItem];
    }

    private function createOrder(User $user, PaymentMode $paymentMode): Order
    {
        $address = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)->post(route('checkout.store'), [
            ...$this->checkoutPayload($address, $paymentMode),
        ])->assertRedirect();

        return Order::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutPayload(Address $address, PaymentMode $paymentMode, string $courierCode = 'jne', string $courierService = 'reg'): array
    {
        return [
            'payment_mode' => $paymentMode->value,
            'address_id' => $address->id,
            'courier_code' => $courierCode,
            'courier_service' => $courierService,
            'shipping_cost' => 15000,
        ];
    }
}
