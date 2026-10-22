<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('account.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_account_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/index', false)
                ->has('user')
                ->has('statistics')
                ->has('phone_verification'),
            );
    }

    public function test_phone_change_sends_otp_without_changing_existing_phone(): void
    {
        $user = User::factory()->create(['phone' => '628111111111']);

        $this->actingAs($user)
            ->post(route('account.phone.update'), ['phone' => '081222222222'])
            ->assertRedirect();

        $this->assertSame('628111111111', $user->refresh()->phone);
        $this->assertDatabaseHas('otp_codes', [
            'phone' => '6281222222222',
            'purpose' => 'phone_change',
        ]);
    }

    public function test_correct_phone_change_otp_updates_phone(): void
    {
        $user = User::factory()->create(['phone' => '628111111111']);

        $this->actingAs($user)
            ->post(route('account.phone.update'), ['phone' => '081222222222']);

        OtpCode::query()
            ->where('phone', '6281222222222')
            ->where('purpose', 'phone_change')
            ->update(['code' => Hash::make('123456')]);

        $this->actingAs($user)
            ->post(route('account.phone.verify'), ['otp' => '123456'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '6281222222222',
        ]);
        $this->assertNotNull($user->refresh()->phone_verified_at);
    }

    public function test_wrong_phone_change_otp_does_not_update_phone(): void
    {
        $user = User::factory()->create(['phone' => '628111111111']);

        $this->actingAs($user)
            ->post(route('account.phone.update'), ['phone' => '081222222222']);

        OtpCode::query()
            ->where('phone', '6281222222222')
            ->where('purpose', 'phone_change')
            ->update(['code' => Hash::make('123456')]);

        $this->actingAs($user)
            ->from(route('account.index'))
            ->post(route('account.phone.verify'), ['otp' => '654321'])
            ->assertSessionHasErrors('otp');

        $this->assertSame('628111111111', $user->refresh()->phone);
    }
}
