<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Campaign;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController
{
    public function index(): Response
    {
        abort_unless(Auth::guard('admin')->user()?->role === AdminRole::Admin, 403);

        $cancelledStatuses = [
            OrderStatus::DibatalkanBuyer->value,
            OrderStatus::DibatalkanSistem->value,
        ];
        $soldItems = OrderItem::query()
            ->select('order_items.campaign_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', $cancelledStatuses)
            ->selectRaw('SUM(order_items.qty) as qty_terjual')
            ->groupBy('order_items.campaign_id');
        $revenues = Payment::query()
            ->select('order_items.campaign_id')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('payments.payment_status', PaymentStatus::Sukses->value)
            ->selectRaw(
                'SUM(CASE WHEN payments.payment_type = ? THEN payments.amount * order_items.total_price / NULLIF(orders.total_amount, 0) ELSE 0 END) as revenue_dp',
                [PaymentType::Dp->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN payments.payment_type = ? THEN payments.amount * order_items.total_price / NULLIF(orders.total_amount, 0) ELSE 0 END) as revenue_pelunasan',
                [PaymentType::Pelunasan->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN payments.payment_type = ? THEN payments.amount * order_items.total_price / NULLIF(orders.total_amount, 0) ELSE 0 END) as revenue_full',
                [PaymentType::Full->value],
            )
            ->groupBy('order_items.campaign_id');
        $reportQuery = Campaign::query()
            ->leftJoinSub($soldItems, 'sold', 'sold.campaign_id', '=', 'campaigns.id')
            ->leftJoinSub($revenues, 'revenues', 'revenues.campaign_id', '=', 'campaigns.id')
            ->select([
                'campaigns.id',
                'campaigns.slug',
                'campaigns.title',
                'campaigns.cost_per_item',
            ])
            ->selectRaw('COALESCE(sold.qty_terjual, 0) as qty_terjual')
            ->selectRaw('COALESCE(revenues.revenue_dp, 0) as revenue_dp')
            ->selectRaw('COALESCE(revenues.revenue_pelunasan, 0) as revenue_pelunasan')
            ->selectRaw('COALESCE(revenues.revenue_full, 0) as revenue_full')
            ->selectRaw('COALESCE(revenues.revenue_dp, 0) + COALESCE(revenues.revenue_pelunasan, 0) + COALESCE(revenues.revenue_full, 0) as revenue_total')
            ->selectRaw('COALESCE(sold.qty_terjual, 0) * COALESCE(campaigns.cost_per_item, 0) as biaya_produksi');
        $allRows = (clone $reportQuery)->get();
        $report = $reportQuery->paginate(15)->withQueryString();

        $report = $report->through(fn (Campaign $campaign): array => $this->reportRow($campaign));

        return Inertia::render('admin/laporan', [
            'report' => $report,
            'summaries' => [
                'revenue_total' => $allRows->sum(fn (object $row): float => (float) $row->revenue_total),
                'biaya_total' => $allRows->sum(fn (object $row): float => (float) $row->biaya_produksi),
                'margin_total' => $allRows->sum(fn (object $row): float => (float) $row->revenue_total - (float) $row->biaya_produksi),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRow(Campaign $row): array
    {
        $revenueTotal = (float) $row->revenue_total;
        $productionCost = (float) $row->biaya_produksi;

        return [
            'id' => $row->id,
            'slug' => $row->slug,
            'title' => $row->title,
            'qty_terjual' => (int) $row->qty_terjual,
            'revenue_dp' => (float) $row->revenue_dp,
            'revenue_pelunasan' => (float) $row->revenue_pelunasan,
            'revenue_full' => (float) $row->revenue_full,
            'revenue_total' => $revenueTotal,
            'biaya_produksi' => $productionCost,
            'margin' => $revenueTotal - $productionCost,
        ];
    }
}
