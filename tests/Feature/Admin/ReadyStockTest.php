<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadyStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Admin]), 'admin');
    }

    public function test_admin_can_update_ready_stock(): void
    {
        $campaignSize = CampaignSize::factory()->for(Campaign::factory()->create())->create(['stock' => 5]);

        $this->from(route('admin.ready-stock.index'))
            ->put(route('admin.ready-stock.update', $campaignSize), ['stock' => 12])
            ->assertRedirect(route('admin.ready-stock.index'))
            ->assertSessionHas('status', 'Stok ready berhasil diperbarui.');

        $this->assertSame(12, $campaignSize->fresh()->stock);
    }

    public function test_ready_stock_must_not_be_negative(): void
    {
        $campaignSize = CampaignSize::factory()->for(Campaign::factory()->create())->create(['stock' => 5]);

        $this->from(route('admin.ready-stock.index'))
            ->put(route('admin.ready-stock.update', $campaignSize), ['stock' => -1])
            ->assertRedirect(route('admin.ready-stock.index'))
            ->assertSessionHasErrors('stock');

        $this->assertSame(5, $campaignSize->fresh()->stock);
    }

    public function test_admin_can_reset_ready_stock_without_deleting_campaign_size(): void
    {
        $campaignSize = CampaignSize::factory()->for(Campaign::factory()->create())->create(['stock' => 5]);

        $this->from(route('admin.ready-stock.index'))
            ->delete(route('admin.ready-stock.destroy', $campaignSize))
            ->assertRedirect(route('admin.ready-stock.index'))
            ->assertSessionHas('status', 'Stok ready direset.');

        $campaignSize = $campaignSize->fresh();

        $this->assertNotNull($campaignSize);
        $this->assertSame(0, $campaignSize->stock);
    }

    public function test_operator_cannot_manage_ready_stock(): void
    {
        $campaignSize = CampaignSize::factory()->for(Campaign::factory()->create())->create(['stock' => 5]);
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Operator]), 'admin');

        $this->put(route('admin.ready-stock.update', $campaignSize), ['stock' => 12])
            ->assertForbidden();

        $this->delete(route('admin.ready-stock.destroy', $campaignSize))
            ->assertForbidden();
    }
}
