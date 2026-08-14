<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController
{
    public function index(): Response
    {
        $cancelledStatuses = [
            OrderStatus::DibatalkanBuyer->value,
            OrderStatus::DibatalkanSistem->value,
        ];
        $recentOrders = Order::query()
            ->with('user')
            ->withCount('items')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
                'items_count' => $order->items_count,
                'user' => [
                    'name' => $order->user->name,
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_campaigns' => Campaign::query()->count(),
                'active_campaigns' => Campaign::query()->where('status', CampaignStatus::Active->value)->count(),
                'total_orders' => Order::query()->count(),
                'pending_orders' => Order::query()->where('status', OrderStatus::MenungguPembayaran->value)->count(),
                'total_revenue' => (float) Payment::query()
                    ->where('payment_status', PaymentStatus::Sukses->value)
                    ->sum('amount'),
                'sold_items' => (int) OrderItem::query()
                    ->whereHas('order', fn (Builder $query): Builder => $query->whereNotIn('status', $cancelledStatuses))
                    ->sum('qty'),
                'ready_stock_count' => (int) CampaignSize::query()
                    ->where('stock', '>', 0)
                    ->sum('stock'),
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
