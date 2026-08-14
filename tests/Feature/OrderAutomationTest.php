<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\Size;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_cancel_cancels_old_unpaid_order(): void
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $order->created_at = now()->subHours(49);
        $order->save();

        $this->artisan('orders:auto-cancel')
            ->assertExitCode(0);

        $this->assertSame(OrderStatus::DibatalkanSistem, $order->refresh()->status);
    }

    public function test_auto_cancel_releases_quota_for_overdue_dp_order(): void
    {
        [$order, $campaign] = $this->paidDpOrder();
        $order->update(['payment_due_at' => now()->subMinute()]);
        $soldCount = $campaign->refresh()->sold_count;

        $this->artisan('orders:auto-cancel')
            ->assertExitCode(0);

        $this->assertSame(OrderStatus::DibatalkanSistem, $order->refresh()->status);
        $this->assertSame($soldCount - 1, $campaign->refresh()->sold_count);
    }

    public function test_payment_reminder_is_sent_once_after_twelve_hours(): void
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $order->created_at = now()->subHours(13);
        $order->save();

        $this->artisan('orders:payment-reminders')
            ->assertExitCode(0);
        $this->artisan('orders:payment-reminders')
            ->assertExitCode(0);

        $this->assertDatabaseCount('wa_messages', 1);
        $this->assertDatabaseHas('wa_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_reminder_dp',
        ]);
    }

    public function test_check_sold_out_marks_full_campaign(): void
    {
        $campaign = Campaign::factory()->active()->create([
            'quota' => 5,
            'sold_count' => 5,
        ]);

        $this->artisan('campaigns:check-sold-out')
            ->assertExitCode(0);

        $this->assertSame(CampaignStatus::SoldOut, $campaign->refresh()->status);
    }

    private function pendingOrder(PaymentMode $paymentMode): Order
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->active()->create(['quota' => 10, 'sold_count' => 0]);
        CampaignSize::factory()->for($campaign)->s()->create(['enabled' => true]);
        CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => 1,
        ]);

        return app(OrderService::class)->createFromCart($user, $paymentMode);
    }

    /**
     * @return array{0: Order, 1: Campaign}
     */
    private function paidDpOrder(): array
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $campaign = $order->items()->first()->campaign;
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        app(OrderService::class)->markDpPaid($order, $payment);

        return [$order, $campaign];
    }
}
