<?php

namespace Tests\Feature;

use App\Enums\WaMessageStatus;
use App\Enums\WebhookProvider;
use App\Jobs\ProcessBiteshipWebhook;
use App\Jobs\ProcessMidtransWebhook;
use App\Jobs\SendWhatsAppMessage;
use App\Models\WaMessage;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class JobFailureLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_biteship_failure_updates_webhook_log(): void
    {
        $log = WebhookLog::factory()->create([
            'provider' => WebhookProvider::Biteship,
        ]);

        (new ProcessBiteshipWebhook($log->id, []))->failed(new RuntimeException('Biteship error'));

        $this->assertDatabaseHas('webhook_logs', [
            'id' => $log->id,
            'processed' => false,
            'error' => 'Biteship error',
        ]);
    }

    public function test_midtrans_failure_updates_webhook_log(): void
    {
        $log = WebhookLog::factory()->create();

        (new ProcessMidtransWebhook($log->id, []))->failed(new RuntimeException('Midtrans error'));

        $this->assertDatabaseHas('webhook_logs', [
            'id' => $log->id,
            'processed' => false,
            'error' => 'Midtrans error',
        ]);
    }

    public function test_whatsapp_failure_marks_message_failed(): void
    {
        $message = WaMessage::factory()->create();

        (new SendWhatsAppMessage($message->id))->failed(new RuntimeException('WhatsApp error'));

        $this->assertDatabaseHas('wa_messages', [
            'id' => $message->id,
            'status' => WaMessageStatus::Failed->value,
            'error' => 'WhatsApp error',
        ]);
    }

    public function test_slack_channel_is_available_without_webhook_url(): void
    {
        $this->assertSame('slack', config('logging.channels.slack.driver'));
        $this->assertNull(config('logging.channels.slack.url'));
    }
}
