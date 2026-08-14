<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookLog;
use App\Services\MidtransService;
use App\Services\OrderService;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMidtransWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $logId, public array $payload) {}

    public function handle(MidtransService $midtransService, OrderService $orderService): void
    {
        $log = WebhookLog::query()->find($this->logId);

        if ($log === null || $log->processed) {
            return;
        }

        $orderId = (string) ($this->payload['order_id'] ?? '');
        $isPelunasan = str_ends_with($orderId, '-2');
        $orderNumber = $isPelunasan ? substr($orderId, 0, -2) : $orderId;
        $order = Order::query()->where('order_number', $orderNumber)->first();

        if ($order === null) {
            $this->finishLog($log, 'Order tidak ditemukan: '.$orderNumber);

            return;
        }

        $statusPayload = $this->payload;

        if (trim((string) config('midtrans.server_key', '')) !== '') {
            $statusPayload = $midtransService->getStatus($orderId);
        }

        $transactionStatus = strtolower((string) ($statusPayload['transaction_status'] ?? ''));
        $transactionId = (string) ($statusPayload['transaction_id'] ?? $this->payload['transaction_id'] ?? '');
        $error = null;

        DB::transaction(function () use (
            $order,
            $orderService,
            $isPelunasan,
            $transactionStatus,
            $transactionId,
            &$error,
        ): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $payment = Payment::query()
                ->where('midtrans_order_id', $isPelunasan ? $lockedOrder->order_number.'-2' : $lockedOrder->order_number)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $error = 'Payment tidak ditemukan: '.$lockedOrder->order_number;

                return;
            }

            if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
                $payment->update([
                    'payment_status' => PaymentStatus::Sukses,
                    'midtrans_transaction_id' => $transactionId !== '' ? $transactionId : $payment->midtrans_transaction_id,
                    'paid_at' => now(),
                ]);

                try {
                    if ($isPelunasan) {
                        if ($lockedOrder->status === OrderStatus::SiapKirim) {
                            $orderService->transition(
                                $lockedOrder,
                                OrderStatus::Lunas,
                                'Pelunasan via Midtrans ('.$transactionStatus.')',
                            );
                        } elseif ($lockedOrder->status !== OrderStatus::Lunas) {
                            throw new DomainException('Transisi status tidak valid.');
                        }
                    } elseif ($payment->payment_type === PaymentType::Dp) {
                        if ($lockedOrder->status === OrderStatus::MenungguPembayaran) {
                            $orderService->markDpPaid($lockedOrder, $payment);
                        } elseif ($lockedOrder->status !== OrderStatus::DpDibayar) {
                            throw new DomainException('Transisi status tidak valid.');
                        }
                    } elseif ($payment->payment_type === PaymentType::Full) {
                        if ($lockedOrder->status === OrderStatus::MenungguPembayaran) {
                            $orderService->markLunas($lockedOrder, $payment);
                        } elseif ($lockedOrder->status !== OrderStatus::Lunas) {
                            throw new DomainException('Transisi status tidak valid.');
                        }
                    } else {
                        throw new DomainException('Jenis pembayaran tidak valid.');
                    }
                } catch (DomainException $exception) {
                    $error = $exception->getMessage();
                }
            } elseif (in_array($transactionStatus, ['expire', 'cancel'], true)) {
                if ($payment->payment_status === PaymentStatus::Menunggu) {
                    $payment->update(['payment_status' => PaymentStatus::Kedaluwarsa]);
                }
            } elseif ($transactionStatus === 'deny') {
                if ($payment->payment_status === PaymentStatus::Menunggu) {
                    $payment->update(['payment_status' => PaymentStatus::Gagal]);
                }
            }
        });

        $log->update([
            'event' => $transactionStatus !== '' ? $transactionStatus : $log->event,
            'processed' => true,
            'error' => $error,
        ]);
    }

    private function finishLog(WebhookLog $log, string $error): void
    {
        $log->update([
            'processed' => true,
            'error' => $error,
        ]);
    }
}
