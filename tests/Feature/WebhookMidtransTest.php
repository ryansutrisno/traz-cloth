<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\Size;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Campaign;
use App\Models\CampaignSize;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookMidtransTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('midtrans.server_key', 'server-secret');
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->payload('TRAZ-20260814-ABCD');
        $payload['signature_key'] = 'invalid';

        $this->postJson(route('webhook.midtrans'), $payload)
            ->assertForbidden();
    }

    public function test_valid_dp_settlement_is_processed_once(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMode::Dp);
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $payload = $this->payload($order->order_number, $payment->id.'-dp');
        Http::fake(['*' => Http::response([
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-dp',
        ], 200)]);

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));

        $order->refresh();
        $payment->refresh();
        $this->assertSame(OrderStatus::DpDibayar, $order->status);
        $this->assertSame(PaymentStatus::Sukses, $payment->payment_status);
        $this->assertSame(1, $order->items()->first()->campaign->refresh()->sold_count);
        $this->assertNotNull($order->payment_due_at);
        $this->assertTrue($log->refresh()->processed);
    }

    public function test_full_settlement_marks_order_lunas_and_reserves_quota(): void
    {
        $order = $this->pendingOrder(PaymentMode::Lunas);
        $payment = Payment::factory()->for($order)->full()->create([
            'amount' => $order->total_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $payload = $this->payload($order->order_number, 'txn-full');
        Http::fake(['*' => Http::response([
            'transaction_status' => 'capture',
            'transaction_id' => 'txn-full',
        ], 200)]);

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));

        $this->assertSame(OrderStatus::Lunas, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Sukses, $payment->refresh()->payment_status);
        $this->assertSame(1, $order->items()->first()->campaign->refresh()->sold_count);
    }

    public function test_settlement_of_second_transaction_marks_ready_order_lunas(): void
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $order->update(['status' => OrderStatus::SiapKirim]);
        $payment = Payment::factory()->for($order)->pelunasan()->create([
            'amount' => (float) $order->total_amount - (float) $order->dp_amount,
            'midtrans_order_id' => $order->order_number.'-2',
        ]);
        $payload = $this->payload($payment->midtrans_order_id, 'txn-settlement-2');
        Http::fake(['*' => Http::response([
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-settlement-2',
        ], 200)]);

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));

        $this->assertSame(OrderStatus::Lunas, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Sukses, $payment->refresh()->payment_status);
    }

    public function test_processed_webhook_is_idempotent(): void
    {
        $order = $this->pendingOrder(PaymentMode::Dp);
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $payload = $this->payload($order->order_number, 'txn-idempotent');
        Http::fake(['*' => Http::response([
            'transaction_status' => 'settlement',
            'transaction_id' => 'txn-idempotent',
        ], 200)]);

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));
        $soldCount = $order->items()->first()->campaign->refresh()->sold_count;

        $this->postJson(route('webhook.midtrans'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok', 'idempotent' => true]);

        $this->assertSame($soldCount, $order->items()->first()->campaign->refresh()->sold_count);
    }

    public function test_unknown_order_is_logged_without_failing_webhook(): void
    {
        $payload = $this->payload('TRAZ-20260814-XXXX', 'txn-unknown');

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));

        $this->assertTrue($log->refresh()->processed);
        $this->assertSame('Order tidak ditemukan: TRAZ-20260814-XXXX', $log->error);
    }

    public function test_dev_webhook_without_server_key_is_processed(): void
    {
        config()->set('midtrans.server_key', null);
        $order = $this->pendingOrder(PaymentMode::Dp);
        $payment = Payment::factory()->for($order)->dp()->create([
            'amount' => $order->dp_amount,
            'midtrans_order_id' => $order->order_number,
        ]);
        $payload = $this->payload($order->order_number, 'txn-dev');
        unset($payload['signature_key']);

        $this->postJson(route('webhook.midtrans'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessMidtransWebhook($log->id, $payload))->handle(app(MidtransService::class), app(OrderService::class));

        $this->assertSame(OrderStatus::DpDibayar, $order->refresh()->status);
        $this->assertSame(PaymentStatus::Sukses, $payment->refresh()->payment_status);
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $orderId, string $transactionId = 'txn-default'): array
    {
        $payload = [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => '10000.00',
            'transaction_status' => 'settlement',
            'transaction_id' => $transactionId,
            'payment_type' => 'credit_card',
        ];
        $serverKey = (string) config('midtrans.server_key', '');

        if ($serverKey !== '') {
            $payload['signature_key'] = hash(
                'sha512',
                $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey,
            );
        }

        return $payload;
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
}
