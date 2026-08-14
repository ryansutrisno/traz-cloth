<?php

namespace Tests\Feature;

use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_cart_requires_authentication(): void
    {
        $this->get(route('cart.index'))
            ->assertRedirect(route('login'));
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
