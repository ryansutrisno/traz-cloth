<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\WebhookProvider;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function midtrans(Request $request): JsonResponse
    {
        $payload = $request->all();
        $serverKey = trim((string) config('midtrans.server_key', ''));

        if ($serverKey !== '') {
            $expectedSignature = hash(
                'sha512',
                (string) ($payload['order_id'] ?? '')
                    .(string) ($payload['status_code'] ?? '')
                    .(string) ($payload['gross_amount'] ?? '')
                    .$serverKey,
            );

            if (! hash_equals($expectedSignature, (string) ($payload['signature_key'] ?? ''))) {
                abort(403, 'Invalid signature');
            }
        } else {
            Log::info('[Midtrans] Webhook diterima dalam mode dev.', $payload);
        }

        $eventId = (string) ($payload['transaction_id'] ?? $payload['order_id'] ?? '');
        $existingLog = WebhookLog::query()
            ->where('provider', WebhookProvider::Midtrans->value)
            ->where('event_id', $eventId)
            ->first();

        if ($existingLog?->processed) {
            return response()->json(['status' => 'ok', 'idempotent' => true]);
        }

        $log = $existingLog ?? WebhookLog::query()->create([
            'provider' => WebhookProvider::Midtrans,
            'event' => $payload['transaction_status'] ?? null,
            'event_id' => $eventId,
            'payload' => $payload,
            'processed' => false,
        ]);

        if ($existingLog !== null) {
            $log->update([
                'event' => $payload['transaction_status'] ?? $log->event,
                'payload' => $payload,
            ]);
        }

        ProcessMidtransWebhook::dispatch($log->id, $payload);

        return response()->json(['status' => 'ok']);
    }
}
