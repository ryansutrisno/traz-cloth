<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Order;
use App\Models\WaMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:payment-reminders')]
#[Description('Kirim pengingat pembayaran order.')]
class SendPaymentReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();

        Order::query()
            ->where('status', OrderStatus::MenungguPembayaran->value)
            ->limit(100)
            ->get()
            ->each(function (Order $order) use ($now): void {
                $hasReminder = $this->hasReminder($order, 'order_reminder_dp');
                $firstReminderAt = $order->created_at->copy()->addHours(12);
                $secondReminderAt = $order->created_at->copy()->addHours(40);

                if (! $hasReminder && $now->greaterThanOrEqualTo($secondReminderAt)) {
                    $this->dispatchReminder($order, 'order_reminder_dp');
                } elseif (! $hasReminder && $now->greaterThanOrEqualTo($firstReminderAt)) {
                    $this->dispatchReminder($order, 'order_reminder_dp');
                }
            });

        Order::query()
            ->whereIn('status', [OrderStatus::DpDibayar->value, OrderStatus::SiapKirim->value])
            ->whereNotNull('payment_due_at')
            ->limit(100)
            ->get()
            ->each(function (Order $order) use ($now): void {
                if ($this->hasReminder($order, 'order_reminder_payment')) {
                    return;
                }

                $firstReminderAt = $order->payment_due_at->copy()->subHours(36);
                $secondReminderAt = $order->payment_due_at->copy()->subHours(8);

                if ($now->greaterThanOrEqualTo($secondReminderAt)
                    || $now->greaterThanOrEqualTo($firstReminderAt)) {
                    SendWhatsAppMessage::dispatchForOrder($order, 'order_reminder_payment', [
                        'remaining_amount' => $this->remainingAmount($order),
                        'due_at' => $order->payment_due_at->toDateTimeString(),
                    ]);
                }
            });

        return self::SUCCESS;
    }

    private function hasReminder(Order $order, string $templateKey): bool
    {
        return WaMessage::query()
            ->where('order_id', $order->id)
            ->where('template_key', $templateKey)
            ->exists();
    }

    private function dispatchReminder(Order $order, string $templateKey): void
    {
        SendWhatsAppMessage::dispatchForOrder($order, $templateKey, [
            'dp_amount' => (string) $order->dp_amount,
            'due_at' => $order->created_at->copy()->addHours(48)->toDateTimeString(),
        ]);
    }

    private function remainingAmount(Order $order): string
    {
        return number_format(
            max(0, (float) $order->total_amount - (float) $order->dp_amount),
            2,
            '.',
            '',
        );
    }
}
