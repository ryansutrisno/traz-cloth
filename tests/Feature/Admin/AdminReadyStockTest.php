<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\Size;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReadyStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Admin]), 'admin');
    }

    public function test_ready_stock_page_only_lists_positive_stock(): void
    {
        $campaign = Campaign::factory()->active()->create();
        CampaignSize::factory()->for($campaign)->s()->create(['stock' => 5]);
        CampaignSize::factory()->for($campaign)->create([
            'size' => Size::M->value,
            'stock' => 0,
        ]);

        $this->get(route('admin.ready-stock.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ready-stock', false)
                ->has('ready_stock.data', 1)
                ->where('ready_stock.data.0.stock', 5)
                ->where('total_stock', 5),
            );

    }
}
