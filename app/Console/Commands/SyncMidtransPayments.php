<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Services\MidtransService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('midtrans:sync-payments')]
#[Description('Sinkronkan status pembayaran Midtrans yang masih menunggu.')]
class SyncMidtransPayments extends Command
{
    public function __construct(private MidtransService $midtransService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Payment::query()
            ->where('payment_status', PaymentStatus::Menunggu->value)
            ->whereNotNull('midtrans_order_id')
            ->limit(100)
            ->get()
            ->each(function (Payment $payment): void {
                try {
                    $status = $this->midtransService->getStatus($payment->midtrans_order_id);
                    $transactionStatus = strtolower((string) ($status['transaction_status'] ?? ''));

                    if (! in_array($transactionStatus, ['settlement', 'capture', 'expire', 'cancel', 'deny'], true)) {
                        return;
                    }

                    $transactionId = (string) ($status['transaction_id'] ?? $payment->midtrans_order_id);
                    $eventId = $transactionId !== '' ? $transactionId : $payment->midtrans_order_id;
                    $log = WebhookLog::query()->firstOrCreate(
                        [
                            'provider' => 'midtrans',
                            'event_id' => $eventId,
                        ],
                        [
                            'event' => $transactionStatus,
                            'payload' => [
                                'order_id' => $payment->midtrans_order_id,
                                'transaction_status' => $transactionStatus,
                                'transaction_id' => $transactionId,
                            ],
                            'processed' => false,
                        ],
                    );

                    if (! $log->processed) {
                        ProcessMidtransWebhook::dispatchSync($log->id, $log->payload);
                    }
                } catch (Throwable $exception) {
                    Log::warning('[Midtrans] Sinkronisasi pembayaran dilewati.', [
                        'payment_id' => $payment->id,
                        'message' => $exception->getMessage(),
                    ]);
                    $this->warn('Payment '.$payment->id.': '.$exception->getMessage());
                }
            });

        return self::SUCCESS;
    }
}
