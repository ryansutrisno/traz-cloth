<?php

namespace App\Jobs;

use App\Enums\WaMessageStatus;
use App\Models\Order;
use App\Models\WaMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $waMessageId) {}

    /**
     * Queue a WhatsApp message for an order.
     *
     * @param  array<string, string>  $extra
     */
    public static function dispatchForOrder(Order $order, string $templateKey, array $extra = []): void
    {
        $order->loadMissing('user');
        $message = WaMessage::query()->create([
            'order_id' => $order->id,
            'phone' => (string) $order->user->phone,
            'template_key' => $templateKey,
            'payload' => array_merge(['order_number' => (string) $order->order_number], $extra),
            'status' => WaMessageStatus::Queued,
        ]);

        self::dispatch($message->id);
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        $waMessage = WaMessage::query()->with('order.user')->find($this->waMessageId);

        if ($waMessage === null || $waMessage->status === WaMessageStatus::Sent) {
            return;
        }

        try {
            $whatsAppService->send($waMessage->phone, $this->renderMessage($waMessage));
            $waMessage->update([
                'status' => WaMessageStatus::Sent,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $waMessage->update([
                'status' => WaMessageStatus::Failed,
                'error' => substr($exception->getMessage(), 0, 200),
            ]);
        }
    }

    public function failed(?Throwable $exception = null): void
    {
        try {
            Log::error('[WhatsApp] Message job failed', [
                'wa_message_id' => $this->waMessageId,
                'exception' => $exception?->getMessage(),
            ]);

            $waMessage = WaMessage::query()->find($this->waMessageId);

            if ($waMessage !== null && $waMessage->status !== WaMessageStatus::Sent) {
                $waMessage->update([
                    'status' => WaMessageStatus::Failed,
                    'error' => substr($exception?->getMessage() ?: 'WhatsApp message job gagal.', 0, 200),
                ]);
            }
        } catch (Throwable) {
            // Failure callbacks must not mask the original queue exception.
        }
    }

    private function renderMessage(WaMessage $waMessage): string
    {
        $payload = array_map(static fn (mixed $value): string => (string) $value, $waMessage->payload);
        $order = $waMessage->getRelationValue('order');
        $name = $payload['name'] ?? ($order instanceof Order ? $order->user->name : 'pelanggan');
        $orderNumber = $payload['order_number'] ?? ($order instanceof Order ? $order->order_number : '');

        return match ($waMessage->template_key) {
            'manual' => $payload['message'] ?? "Halo {$name}, ada pembaruan untuk pesanan {$orderNumber}. — Traz Cloth",
            'order_reminder_dp' => "Halo {$name}, pesanan {$orderNumber} kamu masih menunggu DP {$payload['dp_amount']} (minimal 50%). Batas bayar {$payload['due_at']}. DP hangus jika batal. — Traz Cloth",
            'order_reminder_payment' => "Halo {$name}, sisa pelunasan {$payload['remaining_amount']} untuk pesanan {$orderNumber} sudah bisa dibayar. Batas: {$payload['due_at']}. — Traz Cloth",
            'order_status_ready' => "Halo {$name}, pesanan {$orderNumber} kamu SUDAH SIAP KIRIM! Lakukan pelunasan untuk pengiriman. — Traz Cloth",
            'order_status_sent' => "Halo {$name}, pesanan {$orderNumber} sudah dikirim. — Traz Cloth",
            'order_status_delivered' => "Halo {$name}, pesanan {$orderNumber} sudah diterima. Terima kasih! — Traz Cloth",
            'payment_link' => "Halo {$name}, bayar pelunasan pesanan {$orderNumber} di: {$payload['link']}. — Traz Cloth",
            default => "Halo {$name}, ada pembaruan untuk pesanan {$orderNumber}. — Traz Cloth",
        };
    }
}
