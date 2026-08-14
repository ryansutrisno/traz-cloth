<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_addresses_index_requires_authentication(): void
    {
        $this->get(route('addresses.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_list_and_store_addresses(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['is_default' => false]);

        $this->actingAs($user)
            ->get(route('addresses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('addresses/index', false)
                ->has('addresses', 1)
                ->where('addresses.0.id', $address->id),
            );

        $this->actingAs($user)
            ->post(route('addresses.store'), $this->payload(['label' => 'Rumah', 'is_default' => true]))
            ->assertRedirect(route('addresses.index'));

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'label' => 'Rumah',
            'is_default' => true,
        ]);
    }

    public function test_first_address_is_default_and_new_default_resets_old_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['label' => 'Pertama']));
        $first = Address::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload([
            'label' => 'Kedua',
            'is_default' => true,
        ]));

        $this->assertFalse($first->refresh()->is_default);
        $this->assertTrue(Address::query()->where('label', 'Kedua')->firstOrFail()->is_default);
    }

    public function test_address_validation_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('addresses.index'))
            ->post(route('addresses.store'), ['label' => 'Tidak lengkap'])
            ->assertSessionHasErrors(['recipient_name', 'phone', 'postal_code']);
    }

    public function test_user_can_update_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->default()->create();

        $this->actingAs($user)
            ->patch(route('addresses.update', $address), $this->payload([
                'label' => 'Kantor',
                'city' => 'Jakarta Pusat',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'label' => 'Kantor',
            'city' => 'Jakarta Pusat',
        ]);
    }

    public function test_destroying_default_address_moves_default_to_another_address(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->for($user)->default()->create();
        $other = Address::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('addresses.destroy', $default))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('addresses', ['id' => $default->id]);
        $this->assertTrue($other->refresh()->is_default);
    }

    public function test_user_cannot_modify_another_users_address(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($otherUser)
            ->delete(route('addresses.destroy', $address))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'label' => 'Rumah',
            'recipient_name' => 'Budi Traz',
            'phone' => '6281234567890',
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'district' => 'Kebayoran Baru',
            'subdistrict' => 'Senayan',
            'postal_code' => '12190',
            'full_address' => 'Jl. Contoh No. 1',
            'is_default' => false,
        ], $overrides);

    }
}
