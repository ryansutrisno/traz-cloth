<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_login_screen_can_be_rendered(): void
    {
        $this->get(route('admin.login'))
            ->assertOk();
    }

    public function test_admin_can_login(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.campaigns.index', absolute: false));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertSessionHasErrors('email')
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_guest_redirected_from_admin_pages(): void
    {
        $this->get(route('admin.campaigns.index'))
            ->assertRedirect(route('admin.login'));
    }
}
