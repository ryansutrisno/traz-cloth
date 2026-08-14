<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_view_report_with_margin_calculation(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Admin]), 'admin');
        $user = User::factory()->create();
        $campaign = Campaign::factory()->active()->create([
            'cost_per_item' => 50000,
        ]);
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::Lunas,
            'subtotal' => 200000,
            'total_amount' => 200000,
            'dp_amount' => null,
        ]);
        OrderItem::factory()->for($order)->for($campaign)->create([
            'qty' => 2,
            'unit_price' => 100000,
            'total_price' => 200000,
        ]);
        Payment::factory()->for($order)->full()->sukses()->create([
            'amount' => 200000,
        ]);

        $this->get(route('admin.laporan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/laporan', false)
                ->where('report.data.0.title', $campaign->title)
                ->where('report.data.0.revenue_total', 200000)
                ->where('report.data.0.biaya_produksi', 100000)
                ->where('report.data.0.margin', 100000)
                ->where('summaries.margin_total', 100000),
            );
    }

    public function test_operator_cannot_view_report(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => AdminRole::Operator]), 'admin');

        $this->get(route('admin.laporan.index'))
            ->assertForbidden();
    }
}
