<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FonnteWhatsAppService implements WhatsAppService
{
    public function send(string $phone, string $message): void
    {
        $token = (string) config('whatsapp.drivers.fonnte.token', '');
        $senderId = config('whatsapp.drivers.fonnte.sender_id');

        if (trim($token) === '') {
            Log::info('[WhatsApp] (dev) pesan ke '.$phone.': '.$message);

            return;
        }

        $baseUrl = rtrim((string) config('whatsapp.drivers.fonnte.base_url'), '/');

        try {
            $response = Http::withHeaders(['Authorization' => $token])->post($baseUrl.'/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if (! $response->successful()) {
                Log::warning('[WhatsApp] Fonnte request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'sender_id' => $senderId,
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('[WhatsApp] Fonnte request exception.', [
                'message' => $exception->getMessage(),
                'sender_id' => $senderId,
            ]);
        }
    }
}
