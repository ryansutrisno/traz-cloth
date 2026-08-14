<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\BiteshipService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[Signature('biteship:sync-status')]
#[Description('Sinkronkan status pengiriman Biteship.')]
class SyncBiteshipStatus extends Command
{
    public function __construct(
        private BiteshipService $biteshipService,
        private OrderService $orderService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $processed = 0;

        Order::query()
            ->where('status', OrderStatus::Dikirim->value)
            ->whereNotNull('biteship_order_id')
            ->get()
            ->each(function (Order $order) use (&$processed): void {
                try {
                    $result = $this->biteshipService->getOrderStatus($order->biteship_order_id);

                    if (strtolower((string) ($result['status'] ?? '')) !== 'delivered') {
                        return;
                    }

                    DB::transaction(function () use ($order): void {
                        $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                        if ($lockedOrder->status !== OrderStatus::Delivered) {
                            $this->orderService->transition(
                                $lockedOrder,
                                OrderStatus::Delivered,
                                'Sinkronisasi Biteship: paket diterima',
                            );
                        }

                        $lockedOrder->forceFill(['delivered_at' => now()])->save();
                    });
                    $processed++;
                } catch (DomainException $exception) {
                    Log::warning('[Biteship] Sinkronisasi status dilewati.', [
                        'order_id' => $order->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            });

        $this->info("{$processed} order Biteship diproses.");

        return self::SUCCESS;
    }
}
