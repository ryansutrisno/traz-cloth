<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $recentOrders = Order::query()
            ->where('user_id', $user->id)
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
            ])
            ->values()
            ->all();

        return Inertia::render('dashboard', [
            'recent_orders' => $recentOrders,
            'orders_count' => Order::query()->where('user_id', $user->id)->count(),
            'addresses_count' => $user->addresses()->count(),
        ]);
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
