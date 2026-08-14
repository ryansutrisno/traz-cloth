<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\WebhookProvider;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBiteshipWebhook;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiteshipWebhookController extends Controller
{
    public function biteship(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signatureKey = trim((string) config('biteship.webhook_signature_key', ''));

        if ($signatureKey !== '') {
            $signature = (string) $request->header('x-biteship-signature-key', '');

            if (! hash_equals($signatureKey, $signature)) {
                abort(403);
            }
        }

        $eventId = (string) ($payload['order_id'] ?? '').':'.(string) ($payload['status'] ?? '');
        $existingLog = WebhookLog::query()
            ->where('provider', WebhookProvider::Biteship->value)
            ->where('event_id', $eventId)
            ->first();

        if ($existingLog?->processed) {
            return response()->json(['success' => true]);
        }

        $log = $existingLog ?? WebhookLog::query()->create([
            'provider' => WebhookProvider::Biteship,
            'event' => $payload['status'] ?? null,
            'event_id' => $eventId,
            'payload' => $payload,
            'processed' => false,
        ]);

        if ($existingLog !== null) {
            $log->update([
                'event' => $payload['status'] ?? $log->event,
                'payload' => $payload,
            ]);
        }

        ProcessBiteshipWebhook::dispatch($log->id, $payload);

        return response()->json(['success' => true]);
    }
}
