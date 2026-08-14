<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_view_dashboard(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Admin]), 'admin');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard', false)
                ->has('stats.total_revenue'),
            );
    }

    public function test_operator_can_view_dashboard(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Operator]), 'admin');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard', false)
                ->has('stats.total_revenue'),
            );
    }
}
