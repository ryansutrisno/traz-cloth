<?php

namespace Tests\Feature;

use App\Enums\EmailMessageStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\Size;
use App\Jobs\SendEmailMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\EmailMessage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OrderEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_and_dp_payment_dispatch_transactional_emails(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Dp);

        Queue::assertPushed(SendEmailMessage::class, 1);
        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_created',
        ]);

        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        app(OrderService::class)->markDpPaid($order, $payment);

        Queue::assertPushed(SendEmailMessage::class, 2);
        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'dp_received',
        ]);
    }

    public function test_ready_transition_dispatches_ready_and_payment_link_emails(): void
    {
        Queue::fake();
        $order = $this->paidDpOrder();
        $orderService = app(OrderService::class);

        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);

        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_ready',
        ]);
        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'payment_link',
        ]);
        Queue::assertPushed(SendEmailMessage::class, 4);
    }

    public function test_shipped_delivered_and_cancelled_events_dispatch_emails(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Lunas);
        $payment = Payment::factory()->for($order)->full()->create([
            'amount' => $order->total_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $orderService = app(OrderService::class);
        $orderService->markLunas($order, $payment);
        $orderService->markDikirim($order);
        $orderService->markDelivered($order);

        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_shipped',
        ]);
        $this->assertDatabaseHas('email_messages', [
            'order_id' => $order->id,
            'template_key' => 'order_delivered',
        ]);

        $cancelledOrder = $this->pendingOrder(PaymentMode::Dp);
        $orderService->cancel($cancelledOrder, OrderStatus::DibatalkanBuyer, 'Batal oleh pembeli');

        $this->assertDatabaseHas('email_messages', [
            'order_id' => $cancelledOrder->id,
            'template_key' => 'order_cancelled',
        ]);
    }

    public function test_email_is_skipped_when_user_email_is_empty(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Dp, '');

        Queue::assertNotPushed(SendEmailMessage::class);
        $this->assertDatabaseMissing('email_messages', ['order_id' => $order->id]);
    }

    public function test_handle_marks_email_as_sent(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Dp);
        $message = EmailMessage::query()->where('order_id', $order->id)->firstOrFail();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once();

        (new SendEmailMessage($message->id))->handle($mailer);

        $this->assertSame(EmailMessageStatus::Sent, $message->refresh()->status);
        $this->assertNotNull($message->sent_at);
        $this->assertNull($message->error);
    }

    public function test_handle_marks_email_as_failed_without_throwing(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Dp);
        $message = EmailMessage::query()->where('order_id', $order->id)->firstOrFail();
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        (new SendEmailMessage($message->id))->handle($mailer);

        $this->assertSame(EmailMessageStatus::Failed, $message->refresh()->status);
        $this->assertSame('SMTP unavailable', $message->error);
        $this->assertNull($message->sent_at);
    }

    public function test_email_status_events_do_not_change_whatsapp_behavior(): void
    {
        Queue::fake();
        $order = $this->paidDpOrder();
        $orderService = app(OrderService::class);

        $orderService->markProduksi($order);
        $orderService->markSiapKirim($order);

        Queue::assertPushed(SendWhatsAppMessage::class, 0);
    }

    private function pendingOrder(PaymentMode $paymentMode, ?string $email = null): Order
    {
        $user = User::factory()->create($email === null ? [] : ['email' => $email]);
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

    private function paidDpOrder(): Order
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        app(OrderService::class)->markDpPaid($order, $payment);

        return $order;
    }
}
