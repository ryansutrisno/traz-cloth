<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:auto-cancel')]
#[Description('Batalkan order yang melewati batas pembayaran.')]
class AutoCancelUnpaidOrders extends Command
{
    public function __construct(private OrderService $orderService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $this->cancelOrders(
            Order::query()
                ->where('status', OrderStatus::MenungguPembayaran->value)
                ->where('created_at', '<=', $now->copy()->subHours(48))
                ->limit(100)
                ->get(),
            'Tidak membayar dalam 2x24 jam',
        );
        $this->cancelOrders(
            Order::query()
                ->whereIn('status', [OrderStatus::DpDibayar->value, OrderStatus::SiapKirim->value])
                ->whereNotNull('payment_due_at')
                ->where('payment_due_at', '<=', $now)
                ->limit(100)
                ->get(),
            'Melewati tenggat pelunasan 2x24 jam',
        );

        return self::SUCCESS;
    }

    /**
     * @param  iterable<int, Order>  $orders
     */
    private function cancelOrders(iterable $orders, string $note): void
    {
        foreach ($orders as $order) {
            try {
                $this->orderService->cancel($order, OrderStatus::DibatalkanSistem, $note);
            } catch (DomainException $exception) {
                $this->warn($order->order_number.': '.$exception->getMessage());
            }
        }
    }
}
