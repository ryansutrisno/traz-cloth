@php
    $origin = config('biteship.origin', []);
    $storeName = filled($origin['name'] ?? null) ? $origin['name'] : config('app.name');
    $originPhone = filled($origin['phone'] ?? null) ? $origin['phone'] : '-';
    $originAddress = filled($origin['address'] ?? null) ? $origin['address'] : '-';
    $originPostalCode = filled($origin['postal_code'] ?? null) && (int) $origin['postal_code'] > 0
        ? $origin['postal_code']
        : '-';
    $receiver = $order->address;
    $receiverLocation = collect([
        $receiver?->city,
        $receiver?->province,
        $receiver?->postal_code,
    ])->filter(fn ($value): bool => filled($value))->implode(', ');
    $itemWeight = (int) config('biteship.item_weight_grams', 250);
    $totalWeight = $order->items->sum('qty') * $itemWeight;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label {{ $order->order_number }}</title>
    <style>
        @page { size: 105mm 203mm; margin: 6mm; }
        * { box-sizing: border-box; }
        body { color: #000; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.35; margin: 0; }
        .label { width: 100%; }
        .header { border-bottom: 3px solid #000; padding-bottom: 8px; }
        .store { font-size: 22px; font-weight: 800; }
        .order { font-size: 16px; font-weight: 700; margin-top: 4px; }
        .meta { display: flex; justify-content: space-between; gap: 8px; margin-top: 3px; }
        .courier { font-size: 16px; font-weight: 800; text-transform: uppercase; }
        .resi { border: 3px solid #000; font-family: monospace; font-size: 24px; font-weight: 800; letter-spacing: 3px; margin: 12px 0; overflow-wrap: anywhere; padding: 10px 8px; text-align: center; }
        .section { border-top: 2px solid #000; margin-top: 10px; padding-top: 7px; }
        .section-title { font-size: 12px; font-weight: 800; letter-spacing: 1px; }
        .person { font-size: 16px; font-weight: 700; margin-top: 3px; }
        .text { overflow-wrap: anywhere; }
        .items { border-collapse: collapse; margin-top: 5px; width: 100%; }
        .items th, .items td { border-bottom: 1px solid #999; padding: 4px 0; text-align: left; }
        .items th:last-child, .items td:last-child { text-align: right; }
        .notes { font-size: 12px; margin-top: 8px; }
        .print-button { background: #000; border: 0; color: #fff; cursor: pointer; font-size: 14px; font-weight: 700; margin-top: 14px; padding: 10px 16px; }
        @media print { .no-print { display: none } }
    </style>
</head>
<body>
    <main class="label">
        <header class="header">
            <div class="store">{{ $storeName }}</div>
            <div class="order">ORDER {{ $order->order_number }}</div>
            <div class="meta">
                <span>{{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                <span class="courier">{{ strtoupper($order->courier_code ?: '-') }} / {{ strtoupper($order->courier_service ?: '-') }}</span>
            </div>
        </header>

        <div class="resi">{{ $order->resi_number }}</div>

        <section class="section">
            <div class="section-title">PENGIRIM</div>
            <div class="person">{{ $storeName }}</div>
            <div class="text">{{ $originPhone }}</div>
            <div class="text">{{ $originAddress }}</div>
            <div class="text">Kota: - &middot; Kodepos: {{ $originPostalCode }}</div>
        </section>

        <section class="section">
            <div class="section-title">PENERIMA</div>
            <div class="person">{{ $receiver?->recipient_name ?? $order->user->name }}</div>
            <div class="text">{{ $receiver?->phone ?? $order->user->phone }}</div>
            <div class="text">{{ $receiver?->full_address ?? '-' }}</div>
            <div class="text">{{ $receiverLocation ?: '-' }}</div>
        </section>

        <section class="section">
            <div class="section-title">ISI PAKET</div>
            <table class="items">
                <thead>
                    <tr><th>Nama / Ukuran</th><th>Qty</th></tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->campaign?->title ?? '-' }} / {{ $item->size?->value ?? '-' }}</td>
                            <td>{{ $item->qty }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="notes">
                Berat: {{ $totalWeight }} g<br>
                Dimensi: {{ config('biteship.item_length_cm', '-') }} &times; {{ config('biteship.item_width_cm', '-') }} &times; {{ config('biteship.item_height_cm', '-') }} cm
            </div>
        </section>

        <button class="print-button no-print" type="button" onclick="window.print()">Cetak</button>
    </main>
</body>
</html>
