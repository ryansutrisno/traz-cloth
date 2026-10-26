<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileCampaignsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_passes_when_sold_count_matches_reserved_units(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 2, 'quota' => 10]);
        $order = Order::factory()->dpDibayar()->create();
        OrderItem::factory()->for($order)->for($campaign)->create(['qty' => 2, 'ready_stock_qty' => 0]);
        CampaignSize::factory()->for($campaign)->create(['size' => Size::M->value, 'stock' => 0]);

        $this->artisan('app:reconcile-campaigns')
            ->expectsOutputToContain('Campaign reconciliation OK')
            ->assertExitCode(0);
    }

    public function test_reconciliation_detects_sold_count_drift(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 5, 'quota' => 10]);
        $order = Order::factory()->dpDibayar()->create();
        OrderItem::factory()->for($order)->for($campaign)->create(['qty' => 2, 'ready_stock_qty' => 0]);

        $this->artisan('app:reconcile-campaigns')
            ->expectsOutputToContain('DRIFT')
            ->assertExitCode(1);
    }

    public function test_reconciliation_ignores_cancelled_orders_that_never_reached_production(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 0, 'quota' => 10]);
        $order = Order::factory()->withStatus(OrderStatus::DibatalkanBuyer)->create();
        OrderItem::factory()->for($order)->for($campaign)->create(['qty' => 1, 'ready_stock_qty' => 0]);

        $this->artisan('app:reconcile-campaigns')->assertExitCode(0);
    }

    public function test_reconciliation_counts_cancelled_orders_that_reached_production(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 1, 'quota' => 10]);
        $order = Order::factory()->withStatus(OrderStatus::DibatalkanBuyer)->create();
        OrderItem::factory()->for($order)->for($campaign)->create(['qty' => 1, 'ready_stock_qty' => 0]);
        OrderStatusHistory::factory()->for($order)->create(['status' => OrderStatus::Produksi->value]);

        $this->artisan('app:reconcile-campaigns')
            ->expectsOutputToContain('Campaign reconciliation OK')
            ->assertExitCode(0);
    }

    public function test_reconciliation_excludes_ready_stock_units_from_sold_count(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 1, 'quota' => 10]);
        $order = Order::factory()->dpDibayar()->create();
        OrderItem::factory()->for($order)->for($campaign)->create(['qty' => 3, 'ready_stock_qty' => 2]);

        $this->artisan('app:reconcile-campaigns')
            ->expectsOutputToContain('Campaign reconciliation OK')
            ->assertExitCode(0);
    }

    public function test_reconciliation_detects_negative_ready_stock(): void
    {
        $campaign = Campaign::factory()->create(['sold_count' => 0, 'quota' => 10]);
        CampaignSize::factory()->for($campaign)->create(['size' => Size::L->value, 'stock' => -1]);

        $this->artisan('app:reconcile-campaigns')->assertExitCode(1);
    }
}
