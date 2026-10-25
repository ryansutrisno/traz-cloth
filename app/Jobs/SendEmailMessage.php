<?php

namespace App\Jobs;

use App\Enums\EmailMessageStatus;
use App\Models\Campaign;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendEmailMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $emailMessageId) {}

    /**
     * Queue a transactional email for an order and create its audit row.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function dispatchForOrder(Order $order, string $templateKey, array $extra = []): void
    {
        try {
            $order->loadMissing(['user', 'items.campaign']);
            $user = $order->getRelationValue('user');
            $email = trim((string) ($user instanceof User ? $user->email : null));

            if ($email === '') {
                return;
            }

            $message = EmailMessage::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'email' => $email,
                'template_key' => $templateKey,
                'payload' => self::payloadFor($order, $extra),
                'status' => EmailMessageStatus::Queued,
            ]);

            self::dispatch($message->id);
        } catch (Throwable) {
            // Email delivery must never break an order transition.
        }
    }

    public function handle(Mailer $mailer): void
    {
        $emailMessage = EmailMessage::query()->find($this->emailMessageId);

        if ($emailMessage === null || $emailMessage->status === EmailMessageStatus::Sent) {
            return;
        }

        try {
            $subject = $this->subjectFor($emailMessage);
            $mailer->send('mail.'.$emailMessage->template_key, $emailMessage->payload, function (Message $message) use ($emailMessage, $subject): void {
                $message->to($emailMessage->email)->subject($subject);
            });
            $emailMessage->update([
                'status' => EmailMessageStatus::Sent,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $emailMessage->update([
                'status' => EmailMessageStatus::Failed,
                'error' => substr($exception->getMessage(), 0, 200),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function payloadFor(Order $order, array $extra): array
    {
        $totalAmount = (float) $order->total_amount;
        $dpAmount = $order->dp_amount === null ? null : (float) $order->dp_amount;
        $user = $order->getRelationValue('user');

        return array_merge([
            'store_name' => config('app.name', 'Traz Cloth'),
            'name' => (string) ($user instanceof User ? $user->name : 'Pelanggan'),
            'order_number' => (string) $order->order_number,
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'title' => (string) (($campaign = $item->getRelationValue('campaign')) instanceof Campaign ? $campaign->title : 'Produk'),
                'size' => $item->size->value,
                'qty' => $item->qty,
                'total_price' => (float) $item->total_price,
            ])->values()->all(),
            'subtotal' => (float) $order->subtotal,
            'total_amount' => $totalAmount,
            'dp_amount' => $dpAmount,
            'remaining_amount' => $dpAmount === null ? null : max(0, $totalAmount - $dpAmount),
            'due_at' => $order->payment_due_at?->format('d M Y H:i'),
            'link' => route('orders.show', $order),
            'resi_number' => $order->resi_number,
            'courier' => trim(implode(' ', array_filter([$order->courier_code, $order->courier_service]))),
            'payment_label' => 'Pembayaran',
        ], $extra);
    }

    private function subjectFor(EmailMessage $emailMessage): string
    {
        $orderNumber = (string) ($emailMessage->payload['order_number'] ?? '');

        return match ($emailMessage->template_key) {
            'order_created' => "Pesanan {$orderNumber} berhasil dibuat",
            'dp_received' => "Pembayaran pesanan {$orderNumber} diterima",
            'order_ready' => "Pesanan {$orderNumber} siap dikirim",
            'payment_link' => "Link pelunasan pesanan {$orderNumber}",
            'order_shipped' => "Pesanan {$orderNumber} telah dikirim",
            'order_delivered' => "Pesanan {$orderNumber} telah diterima",
            'order_cancelled' => "Pesanan {$orderNumber} dibatalkan",
            default => "Pembaruan pesanan {$orderNumber}",
        };
    }
}
