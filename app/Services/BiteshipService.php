<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use DomainException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BiteshipService
{
    /** @param array<int, mixed> $items */
    public function __construct(private array $items = []) {}

    /**
     * Get the HTTP headers required by the Biteship API.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'authorization' => (string) config('biteship.api_key', ''),
            'content-type' => 'application/json',
            'accept' => 'application/json',
        ];
    }

    private function isDev(): bool
    {
        return config('biteship.api_key') === '';
    }

    /**
     * Get available courier rates for a destination postal code.
     *
     * @param  array<int, mixed>  $items
     * @param  array<int, string>|null  $couriers
     * @return array<int, array{courier_code: string, courier_service_code: string, service_type: string, price: int, duration: mixed, shipment_duration_range: mixed}>
     */
    public function getRates(array $items, string $destinationPostalCode, ?array $couriers = null): array
    {
        $normalizedItems = $this->normalizeItems($items ?: $this->items);
        $courierList = $couriers ?? config('biteship.couriers', []);
        $courierList = array_values(array_filter(array_map(
            static fn (mixed $courier): string => trim((string) $courier),
            $courierList,
        )));

        if ($this->isDev()) {
            Log::info('[Biteship] (dev) Rates dihitung.', [
                'destination_postal_code' => $destinationPostalCode,
                'items' => $normalizedItems,
            ]);

            return [[
                'courier_code' => 'jne',
                'courier_service_code' => 'reg',
                'service_type' => 'standard',
                'price' => 15000,
                'duration' => '2 - 3 days',
                'shipment_duration_range' => '2 - 3 days',
            ]];
        }

        try {
            $response = Http::retry(3, 200, throw: false)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/v1/rates/couriers', [
                    'couriers' => implode(',', $courierList),
                    'items' => $normalizedItems,
                    'origin_postal_code' => (int) config('biteship.origin.postal_code', 0),
                    'destination_postal_code' => (int) $destinationPostalCode,
                ]);
        } catch (Throwable $exception) {
            throw new DomainException('Gagal menghitung ongkir Biteship', previous: $exception);
        }

        if (! $response->successful()) {
            throw new DomainException($this->errorMessage($response, 'Gagal menghitung ongkir Biteship'));
        }

        $pricing = $response->json('pricing', []);

        if (! is_array($pricing)) {
            return [];
        }

        return array_map(
            static fn (mixed $rate): array => [
                'courier_code' => (string) ($rate['courier_code'] ?? ''),
                'courier_service_code' => (string) ($rate['courier_service_code'] ?? ''),
                'service_type' => (string) ($rate['service_type'] ?? ''),
                'price' => (int) ($rate['price'] ?? 0),
                'duration' => $rate['duration'] ?? null,
                'shipment_duration_range' => $rate['shipment_duration_range'] ?? null,
            ],
            $pricing,
        );
    }

    /**
     * Book a courier shipment for an order.
     *
     * @param  array{address?: Address, note?: string|null}  $options
     * @return array{order_id: string, tracking_id: string|null, waybill_id: string|null, status: string, price: int|float}
     */
    public function bookCourier(Order $order, string $courierCompany, string $courierType, array $options = []): array
    {
        $order->loadMissing(['items.campaign', 'user', 'address']);
        $address = $options['address'] ?? $order->address;

        if (! $address instanceof Address) {
            throw new DomainException('Alamat pengiriman order tidak tersedia.');
        }

        $payload = [
            'shipper_contact_name' => config('biteship.origin.name', ''),
            'shipper_contact_phone' => config('biteship.origin.phone', ''),
            'shipper_contact_email' => config('mail.from.address'),
            'shipper_organization' => config('biteship.origin.name', ''),
            'origin_contact_name' => config('biteship.origin.name', ''),
            'origin_contact_phone' => config('biteship.origin.phone', ''),
            'origin_address' => config('biteship.origin.address', ''),
            'origin_postal_code' => (int) config('biteship.origin.postal_code', 0),
            'destination_contact_name' => $address->recipient_name,
            'destination_contact_phone' => $address->phone,
            'destination_address' => $address->full_address,
            'destination_postal_code' => (int) $address->postal_code,
            'courier_company' => $courierCompany,
            'courier_type' => $courierType,
            'delivery_type' => 'now',
            'order_note' => $options['note'] ?? $order->buyer_notes,
            'reference_id' => $order->order_number,
            'items' => $order->items->map(
                fn (OrderItem $item): array => $this->bookingItem($item),
            )->all(),
        ];

        if ($this->isDev()) {
            Log::info('[Biteship] (dev) Courier dibooking.', [
                'order_number' => $order->order_number,
                'courier_company' => $courierCompany,
                'courier_type' => $courierType,
            ]);

            return [
                'order_id' => 'dev-'.$order->order_number,
                'tracking_id' => null,
                'waybill_id' => 'DEV-'.$order->order_number,
                'status' => 'confirmed',
                'price' => 0,
            ];
        }

        try {
            $response = Http::retry(3, 200, throw: false)
                ->withHeaders($this->headers())
                ->post($this->baseUrl().'/v1/orders', $payload);
        } catch (Throwable $exception) {
            throw new DomainException('Gagal membuat order Biteship', previous: $exception);
        }

        if (! $response->successful()) {
            throw new DomainException($this->errorMessage($response, 'Gagal membuat order Biteship'));
        }

        $data = $response->json();
        $courier = is_array($data['courier'] ?? null) ? $data['courier'] : [];

        return [
            'order_id' => (string) ($data['id'] ?? ''),
            'tracking_id' => isset($courier['tracking_id']) ? (string) $courier['tracking_id'] : null,
            'waybill_id' => isset($courier['waybill_id']) ? (string) $courier['waybill_id'] : null,
            'status' => (string) ($data['status'] ?? 'confirmed'),
            'price' => $data['price'] ?? 0,
        ];
    }

    /**
     * Get the current status and courier history for a Biteship order.
     *
     * @return array{status: string|null, history: array<int, mixed>}
     */
    public function getOrderStatus(string $biteshipOrderId): array
    {
        if ($this->isDev()) {
            Log::info('[Biteship] (dev) Status order dibaca.', [
                'biteship_order_id' => $biteshipOrderId,
            ]);

            return [
                'status' => 'delivered',
                'history' => [],
            ];
        }

        try {
            $response = Http::retry(3, 200, throw: false)
                ->withHeaders($this->headers())
                ->get($this->baseUrl().'/v1/orders/'.rawurlencode($biteshipOrderId));
        } catch (Throwable $exception) {
            throw new DomainException('Gagal membaca status order Biteship', previous: $exception);
        }

        if (! $response->successful()) {
            throw new DomainException($this->errorMessage($response, 'Gagal membaca status order Biteship'));
        }

        $data = $response->json();
        $history = $data['courier']['history'] ?? [];

        return [
            'status' => isset($data['status']) ? (string) $data['status'] : null,
            'history' => is_array($history) ? $history : [],
        ];
    }

    /**
     * Search Indonesian shipping areas by keyword.
     *
     * @return array<int, mixed>
     */
    public function searchAreas(string $keyword): array
    {
        if ($this->isDev()) {
            return [];
        }

        try {
            $response = Http::retry(3, 200, throw: false)
                ->withHeaders($this->headers())
                ->get($this->baseUrl().'/v1/maps/areas', [
                    'countries' => 'ID',
                    'input' => $keyword,
                    'type' => 'single',
                ]);

            if (! $response->successful()) {
                Log::warning('[Biteship] Gagal mencari area.', [
                    'keyword' => $keyword,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $areas = $response->json('areas', []);

            return is_array($areas) ? $areas : [];
        } catch (Throwable $exception) {
            Log::warning('[Biteship] Exception saat mencari area.', [
                'keyword' => $keyword,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('biteship.base_url', 'https://api.biteship.com'), '/');
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{name: string, value: int, quantity: int, weight: int}>
     */
    private function normalizeItems(array $items): array
    {
        return array_values(array_map(function (mixed $item): array {
            if ($item instanceof OrderItem) {
                $item->loadMissing('campaign');

                return [
                    'name' => $item->campaign->title.' - '.$item->size->value,
                    'value' => (int) round((float) $item->unit_price),
                    'quantity' => (int) $item->qty,
                    'weight' => (int) config('biteship.item_weight_grams', 250) * (int) $item->qty,
                ];
            }

            $item = is_array($item) ? $item : [];

            return [
                'name' => (string) ($item['name'] ?? 'Item'),
                'value' => (int) ($item['value'] ?? $item['price'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? $item['qty'] ?? 1),
                'weight' => (int) ($item['weight'] ?? config('biteship.item_weight_grams', 250)),
            ];
        }, $items));
    }

    /**
     * @return array{name: string, value: int, quantity: int, weight: int, description: string, category: string, length: int, width: int, height: int}
     */
    private function bookingItem(OrderItem $item): array
    {
        return [
            'name' => $item->campaign->title.' - '.$item->size->value,
            'value' => (int) round((float) $item->unit_price),
            'quantity' => (int) $item->qty,
            'weight' => (int) config('biteship.item_weight_grams', 250) * (int) $item->qty,
            'description' => $item->campaign->title,
            'category' => 'fashion',
            'length' => (int) config('biteship.item_length_cm', 30),
            'width' => (int) config('biteship.item_width_cm', 25),
            'height' => (int) config('biteship.item_height_cm', 5),
        ];
    }

    private function errorMessage(Response $response, string $fallback): string
    {
        $message = $response->json('message')
            ?? $response->json('error_messages')
            ?? $response->json('error');

        if (is_array($message)) {
            $message = reset($message);
        }

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
