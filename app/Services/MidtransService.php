<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Throwable;

class MidtransService
{
    /**
     * Create a Midtrans Snap token for the selected payment transaction.
     *
     * @param  array{finish?: string, error?: string, unfinish?: string}  $callbacks
     * @return array{token: string, redirect_url: string}
     */
    public function createSnapToken(Order $order, PaymentType $type, array $callbacks = []): array
    {
        $serverKey = trim((string) config('midtrans.server_key', ''));

        if ($serverKey === '') {
            $result = [
                'token' => 'dev-token-'.$order->order_number,
                'redirect_url' => $this->developmentRedirectUrl($order),
            ];

            Log::info('[Midtrans] (dev) Snap token dibuat.', [
                'order_number' => $order->order_number,
                'payment_type' => $type->value,
            ]);

            return $result;
        }

        $order->loadMissing(['items.campaign', 'user']);
        $orderId = $type === PaymentType::Pelunasan
            ? $order->order_number.'-2'
            : $order->order_number;
        $grossAmount = $this->grossAmount($order, $type);
        $payload = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $order->items->map(
                fn (OrderItem $item): array => [
                    'id' => $item->campaign_id,
                    'price' => (int) round((float) $item->unit_price),
                    'quantity' => $item->qty,
                    'name' => $item->campaign->title.' - '.$item->size->value,
                    'category' => 'Apparel',
                ],
            )->all(),
            'customer_details' => [
                'first_name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $this->formatPhone($order->user->phone),
            ],
            'credit_card' => [
                'secure' => true,
            ],
            'expiry' => [
                'unit' => config('midtrans.expiry_unit', 'hours'),
                'duration' => (int) config('midtrans.expiry_duration', 24),
            ],
        ];
        $callbackPayload = array_intersect_key($callbacks, array_flip(['finish', 'error', 'unfinish']));

        if ($callbackPayload !== []) {
            $payload['callbacks'] = $callbackPayload;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.base64_encode($serverKey.':'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post((string) config('midtrans.base_url'), $payload);

            if (! $response->successful()) {
                $errorMessages = $response->json('error_messages') ?? $response->body();

                throw new RuntimeException('Midtrans API error: '.(is_string($errorMessages) ? $errorMessages : json_encode($errorMessages)));
            }

            $token = $response->json('token');
            $redirectUrl = $response->json('redirect_url');

            if (! is_string($token) || ! is_string($redirectUrl)) {
                throw new RuntimeException('Respons Midtrans tidak berisi token atau redirect URL.');
            }

            return [
                'token' => $token,
                'redirect_url' => $redirectUrl,
            ];
        } catch (Throwable $exception) {
            Log::error('[Midtrans] Gagal membuat Snap token.', [
                'order_number' => $order->order_number,
                'payment_type' => $type->value,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function grossAmount(Order $order, PaymentType $type): int
    {
        return match ($type) {
            PaymentType::Dp => max(0, (int) round((float) $order->dp_amount)),
            PaymentType::Full => (int) round((float) $order->total_amount),
            PaymentType::Pelunasan => max(
                0,
                (int) round((float) $order->total_amount - (float) $order->dp_amount),
            ),
        };
    }

    private function formatPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($normalizedPhone, '0')) {
            $normalizedPhone = '62'.substr($normalizedPhone, 1);
        }

        if (! str_starts_with($normalizedPhone, '62')) {
            $normalizedPhone = '62'.$normalizedPhone;
        }

        return '+'.$normalizedPhone;
    }

    private function developmentRedirectUrl(Order $order): string
    {
        if (Route::has('checkout.dev')) {
            return route('checkout.dev', $order);
        }

        return '/checkout/dev/'.$order->order_number;
    }
}
