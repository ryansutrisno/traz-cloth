<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private object $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->whatsapp = new class implements WhatsAppService
        {
            /** @var array<int, array{phone: string, message: string}> */
            public array $sent = [];

            public function send(string $phone, string $message): void
            {
                $this->sent[] = ['phone' => $phone, 'message' => $message];
            }
        };
        $this->app->instance(WhatsAppService::class, $this->whatsapp);
    }

    public function test_guest_can_add_item_to_cart(): void
    {
        $campaign = $this->campaignWithSize();

        $this->post(route('cart.add'), [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 2,
        ])->assertRedirect()->assertSessionHas('guest_cart.0', [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 2,
        ]);
    }

    public function test_guest_cart_index_displays_session_item(): void
    {
        $campaign = $this->campaignWithSize();
        $this->post(route('cart.add'), [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cart/index', false)
                ->has('items', 1)
                ->where('items.0.id', 0),
            );
    }

    public function test_guest_can_update_and_remove_cart_item_by_session_index(): void
    {
        $campaign = $this->campaignWithSize();
        $this->post(route('cart.add'), [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]);

        $this->patch(route('cart.update', 0), ['qty' => 3])
            ->assertRedirect()
            ->assertSessionHas('guest_cart.0.qty', 3);

        $this->delete(route('cart.destroy', 0))
            ->assertRedirect()
            ->assertSessionMissing('guest_cart');
    }

    public function test_guest_cart_rejects_quantity_over_quota(): void
    {
        $campaign = $this->campaignWithSize(quota: 1);

        $this->from(route('cart.index'))
            ->post(route('cart.add'), [
                'campaign_id' => $campaign->id,
                'size' => Size::S->value,
                'qty' => 2,
            ])
            ->assertSessionHasErrors('qty');
    }

    public function test_user_can_add_item_and_merge_matching_items(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize();

        $this->actingAs($user)
            ->post(route('cart.add'), [
                'campaign_id' => $campaign->id,
                'size' => Size::S->value,
                'qty' => 2,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('cart.add'), [
                'campaign_id' => $campaign->id,
                'size' => Size::S->value,
                'qty' => 3,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 5,
        ]);
    }

    public function test_user_cannot_add_disabled_size(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize(enabled: false);

        $this->actingAs($user)
            ->from(route('cart.index'))
            ->post(route('cart.add'), [
                'campaign_id' => $campaign->id,
                'size' => Size::S->value,
                'qty' => 1,
            ])
            ->assertSessionHasErrors('size');
    }

    public function test_user_cannot_add_quantity_over_campaign_quota(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize(quota: 2);

        $this->actingAs($user)
            ->from(route('cart.index'))
            ->post(route('cart.add'), [
                'campaign_id' => $campaign->id,
                'size' => Size::S->value,
                'qty' => 3,
            ])
            ->assertSessionHasErrors('qty');
    }

    public function test_user_can_update_cart_quantity_and_invalid_quantity_is_rejected(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize();
        $cartItem = CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]);

        $this->actingAs($user)
            ->patch(route('cart.update', $cartItem), ['qty' => 3])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'qty' => 3]);

        foreach ([0, -1] as $invalidQty) {
            $this->actingAs($user)
                ->from(route('cart.index'))
                ->patch(route('cart.update', $cartItem), ['qty' => $invalidQty])
                ->assertSessionHasErrors('qty');
        }

        $this->assertDatabaseHas('cart_items', ['id' => $cartItem->id, 'qty' => 3]);
    }

    public function test_user_can_remove_cart_item(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize();
        $cartItem = CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
        ]);

        $this->actingAs($user)
            ->delete(route('cart.destroy', $cartItem))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cart_items', ['id' => $cartItem->id]);
    }

    public function test_empty_cart_is_rendered_without_errors(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cart/index', false)
                ->where('items', [])
                ->where('total', 0)
                ->where('empty', true),
            );
    }

    public function test_guest_cart_merges_when_buyer_logs_in(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize();
        $this->post(route('cart.add'), [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 2,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 2,
        ]);
        $this->assertNull(session('guest_cart'));
    }

    public function test_guest_cart_merges_after_registration_verification(): void
    {
        $campaign = $this->campaignWithSize();
        $this->post(route('cart.add'), [
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]);
        $this->post(route('register.store'), [
            'name' => 'Guest Cart User',
            'email' => 'guest-cart@example.com',
            'phone' => '081234567890',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        preg_match('/(\d{6})/', $this->whatsapp->sent[0]['message'], $matches);

        $this->post(route('verify-otp.check'), ['otp' => $matches[1]])
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'guest-cart@example.com')->firstOrFail();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'qty' => 1,
        ]);
        $this->assertNull(session('guest_cart'));
    }

    public function test_guest_cart_skips_inactive_campaign_on_login_merge(): void
    {
        $user = User::factory()->create();
        $campaign = $this->campaignWithSize();
        $campaign->update(['status' => CampaignStatus::Draft]);
        $this->withSession(['guest_cart' => [[
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]]])->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertNull(session('guest_cart'));
    }

    private function campaignWithSize(
        int $quota = 20,
        bool $enabled = true,
    ): Campaign {
        $campaign = Campaign::factory()->active()->create([
            'quota' => $quota,
            'sold_count' => 0,
        ]);

        CampaignSize::factory()->for($campaign)->s()->create([
            'enabled' => $enabled,
            'stock' => 0,
        ]);

        return $campaign;
    }
}
