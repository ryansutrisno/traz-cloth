<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\Size;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaMessage;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_ready_order_sets_payment_due_and_accepts_remaining_payment(): void
    {
        [$order] = $this->paidDpOrder();
        $orderService = app(OrderService::class);
        $orderService->markProduksi($order);
        $order->update(['payment_due_at' => null]);

        $orderService->markSiapKirim($order);
        $order->refresh();

        $this->assertNotNull($order->payment_due_at);
        $this->assertTrue($order->payment_due_at->between(now()->addHours(47), now()->addHours(49)));

        $payment = Payment::factory()->for($order)->pelunasan()->create([
            'amount' => (float) $order->total_amount - (float) $order->dp_amount,
            'midtrans_order_id' => $order->order_number.'-2',
        ]);
        $orderService->markLunas($order, $payment);

        $this->assertSame(OrderStatus::Lunas, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Sukses, $payment->refresh()->payment_status);
        $this->assertNull($order->payment_due_at);
    }

    public function test_canceling_production_order_returns_item_to_ready_stock(): void
    {
        [$order, $campaign] = $this->paidDpOrder();
        app(OrderService::class)->markProduksi($order);

        app(OrderService::class)->cancel($order, OrderStatus::DibatalkanBuyer);

        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
        $this->assertSame(1, $campaign->refresh()->sold_count);
        $this->assertSame(1, $this->readyStock($campaign));
    }

    public function test_canceling_ready_order_returns_item_to_ready_stock(): void
    {
        [$order, $campaign] = $this->paidDpOrder();
        $orderService = app(OrderService::class);
        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);

        app(OrderService::class)->cancel($order, OrderStatus::DibatalkanBuyer);

        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
        $this->assertSame(1, $campaign->refresh()->sold_count);
        $this->assertSame(1, $this->readyStock($campaign));
    }

    public function test_canceling_full_order_returns_reserved_quota(): void
    {
        $order = $this->pendingOrder(PaymentMode::Lunas);
        $campaign = $order->items()->firstOrFail()->campaign;
        $payment = Payment::factory()->for($order)->full()->create([
            'amount' => $order->total_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $orderService = app(OrderService::class);
        $orderService->markLunas($order, $payment);

        $this->assertSame(1, $campaign->refresh()->sold_count);
        $orderService->cancel($order, OrderStatus::DibatalkanBuyer);

        $this->assertSame(OrderStatus::DibatalkanBuyer, $order->refresh()->status);
        $this->assertSame(0, $campaign->refresh()->sold_count);
    }

    public function test_canceling_mixed_ready_stock_order_releases_each_reserved_resource_once(): void
    {
        $order = $this->pendingOrder(PaymentMode::Lunas, 2);
        $campaign = $order->items()->firstOrFail()->campaign;
        $campaign->sizes()
            ->where('size', Size::S->value)
            ->update(['stock' => 1]);
        $payment = Payment::factory()->for($order)->full()->create([
            'amount' => $order->total_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $orderService = app(OrderService::class);
        $orderService->markLunas($order, $payment);

        $this->assertSame(1, $order->items()->firstOrFail()->refresh()->ready_stock_qty);
        $this->assertSame(1, $campaign->refresh()->sold_count);
        $this->assertSame(0, $this->readyStock($campaign));

        $orderService->cancel($order, OrderStatus::DibatalkanBuyer);

        $this->assertSame(0, $campaign->refresh()->sold_count);
        $this->assertSame(1, $this->readyStock($campaign));
    }

    public function test_canceling_dp_order_releases_full_quota_reservation(): void
    {
        [$order, $campaign] = $this->paidDpOrder();

        app(OrderService::class)->cancel($order, OrderStatus::DibatalkanBuyer);

        $this->assertSame(0, $campaign->refresh()->sold_count);
        $this->assertSame(0, $order->items()->firstOrFail()->refresh()->ready_stock_qty);
    }

    public function test_auto_cancel_ready_order_moves_item_to_ready_stock(): void
    {
        [$order, $campaign] = $this->paidDpOrder();
        $orderService = app(OrderService::class);
        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);
        $order->update(['payment_due_at' => now()->subMinute()]);

        $this->artisan('orders:auto-cancel')
            ->assertExitCode(0);

        $this->assertSame(OrderStatus::DibatalkanSistem, $order->refresh()->status);
        $this->assertSame(1, $campaign->refresh()->sold_count);
        $this->assertSame(1, $this->readyStock($campaign));
    }

    public function test_ready_transition_dispatches_status_and_payment_link_notifications(): void
    {
        Queue::fake();
        [$order] = $this->paidDpOrder('081234567890');
        $orderService = app(OrderService::class);

        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);

        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        $this->assertDatabaseHas('wa_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_status_ready',
        ]);
        $paymentLink = WaMessage::query()
            ->where('order_id', $order->id)
            ->where('template_key', 'payment_link')
            ->firstOrFail();

        $this->assertSame(route('orders.show', $order), $paymentLink->payload['link']);
    }

    public function test_sent_and_delivered_transitions_dispatch_status_notifications(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Lunas, 1, '081234567890');
        $payment = Payment::factory()->for($order)->full()->create([
            'amount' => $order->total_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $orderService = app(OrderService::class);
        $orderService->markLunas($order, $payment);

        $orderService->markDikirim($order);
        $orderService->markDelivered($order);

        Queue::assertPushed(SendWhatsAppMessage::class, 2);
        $this->assertDatabaseHas('wa_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_status_sent',
        ]);
        $this->assertDatabaseHas('wa_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_status_delivered',
        ]);
    }

    public function test_cancellation_does_not_dispatch_status_notification(): void
    {
        Queue::fake();
        [$order] = $this->paidDpOrder('081234567890');

        app(OrderService::class)->cancel($order, OrderStatus::DibatalkanBuyer);

        Queue::assertNotPushed(SendWhatsAppMessage::class);
    }

    public function test_status_notification_is_not_dispatched_when_phone_is_empty(): void
    {
        Queue::fake();
        [$order] = $this->paidDpOrder();
        $orderService = app(OrderService::class);

        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);

        Queue::assertNotPushed(SendWhatsAppMessage::class);
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

    private function pendingOrder(PaymentMode $paymentMode, int $qty = 1, ?string $phone = null): Order
    {
        $user = User::factory()->create($phone === null ? [] : ['phone' => $phone]);
        $campaign = Campaign::factory()->active()->create(['quota' => 10, 'sold_count' => 0]);
        CampaignSize::factory()->for($campaign)->s()->create(['enabled' => true]);
        CartItem::factory()->create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'size' => Size::S->value,
            'qty' => $qty,
        ]);

        return app(OrderService::class)->createFromCart($user, $paymentMode);
    }

    /**
     * @return array{0: Order, 1: Campaign}
     */
    private function paidDpOrder(?string $phone = null): array
    {
        $order = $this->pendingOrder(PaymentMode::Dp, 1, $phone);
        $campaign = $order->items()->first()->campaign;
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        app(OrderService::class)->markDpPaid($order, $payment);

        return [$order, $campaign];
    }

    private function readyStock(Campaign $campaign): int
    {
        return (int) $campaign->sizes()
            ->where('size', Size::S->value)
            ->value('stock');
    }
}
