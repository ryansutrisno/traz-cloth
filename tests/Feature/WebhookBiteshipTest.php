<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\ProcessBiteshipWebhook;
use App\Models\Order;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookBiteshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('biteship.webhook_signature_key', '');
    }

    public function test_dev_webhook_delivers_order(): void
    {
        Queue::fake();
        $order = Order::factory()->for(User::factory())->create([
            'status' => OrderStatus::Dikirim,
            'biteship_order_id' => 'dev-biteship-1',
        ]);
        $payload = ['order_id' => $order->biteship_order_id, 'status' => 'delivered'];

        $this->postJson(route('webhook.biteship'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessBiteshipWebhook($log->id, $payload))->handle(app(OrderService::class));

        $this->assertSame(OrderStatus::Delivered, $order->refresh()->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertTrue($log->refresh()->processed);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config()->set('biteship.webhook_signature_key', 'secret');

        $this->withHeader('x-biteship-signature-key', 'wrong')
            ->postJson(route('webhook.biteship'), [
                'order_id' => 'biteship-1',
                'status' => 'delivered',
            ])
            ->assertForbidden();
    }

    public function test_processed_event_is_idempotent(): void
    {
        Queue::fake();
        $payload = ['order_id' => 'dev-biteship-2', 'status' => 'confirmed'];

        $this->postJson(route('webhook.biteship'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        $log->update(['processed' => true]);

        $this->postJson(route('webhook.biteship'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('webhook_logs', 1);
    }

    public function test_unknown_order_is_logged_as_processed_error(): void
    {
        Queue::fake();
        $payload = ['order_id' => 'dev-unknown', 'status' => 'delivered'];

        $this->postJson(route('webhook.biteship'), $payload)->assertOk();
        $log = WebhookLog::query()->firstOrFail();
        (new ProcessBiteshipWebhook($log->id, $payload))->handle(app(OrderService::class));

        $this->assertTrue($log->refresh()->processed);
        $this->assertNotNull($log->error);
    }
}
