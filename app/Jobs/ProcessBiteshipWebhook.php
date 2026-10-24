<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\WebhookLog;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBiteshipWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $logId, public array $payload) {}

    public function handle(OrderService $orderService): void
    {
        $log = WebhookLog::query()->find($this->logId);

        if ($log === null || $log->processed) {
            return;
        }

        try {
            $biteshipOrderId = $this->payload['order_id'] ?? null;
            $order = Order::query()->where('biteship_order_id', $biteshipOrderId)->first();

            if ($order === null) {
                $error = 'Order Biteship tidak ditemukan: '.(string) $biteshipOrderId;
                Log::warning($error);
                $log->update(['processed' => true, 'error' => $error]);

                return;
            }

            $status = strtolower((string) ($this->payload['status'] ?? ''));
            $waybillId = $this->payload['waybill_id'] ?? data_get($this->payload, 'courier.waybill_id');

            if ($status === 'delivered') {
                DB::transaction(function () use ($order, $orderService): void {
                    $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

                    if ($lockedOrder->status !== OrderStatus::Delivered) {
                        $orderService->transition(
                            $lockedOrder,
                            OrderStatus::Delivered,
                            'Webhook Biteship: paket diterima',
                        );
                    }

                    $lockedOrder->forceFill(['delivered_at' => now()])->save();
                });
            } else {
                Log::info('[Biteship] Status webhook diterima.', [
                    'order_id' => $order->id,
                    'status' => $status,
                ]);

                if ($waybillId !== null && $order->resi_number === null) {
                    $order->forceFill(['resi_number' => (string) $waybillId])->save();
                }
            }

            $log->update(['processed' => true, 'error' => null]);
        } catch (Throwable $exception) {
            $log->update([
                'processed' => false,
                'error' => substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        try {
            Log::error('[Biteship] Webhook job failed', [
                'log_id' => $this->logId,
                'exception' => $exception?->getMessage(),
            ]);

            $log = WebhookLog::query()->find($this->logId);

            if ($log !== null) {
                $log->update([
                    'processed' => false,
                    'error' => substr($exception?->getMessage() ?: 'Biteship webhook job gagal.', 0, 500),
                ]);
            }
        } catch (Throwable) {
            // Failure callbacks must not mask the original queue exception.
        }
    }
}
