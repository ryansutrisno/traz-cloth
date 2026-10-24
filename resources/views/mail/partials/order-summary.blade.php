<p><strong>Nomor pesanan:</strong> {{ $order_number }}</p>
<p><strong>Nama:</strong> {{ $name }}</p>

<p><strong>Item:</strong></p>
<ul>
    @forelse ($items as $item)
        <li>{{ $item['title'] }} ({{ $item['size'] }}) × {{ $item['qty'] }} — Rp {{ number_format($item['total_price'], 0, ',', '.') }}</li>
    @empty
        <li>Detail item tidak tersedia.</li>
    @endforelse
</ul>

<p><strong>Total:</strong> Rp {{ number_format($total_amount, 0, ',', '.') }}</p>
@if ($dp_amount !== null)
    <p><strong>DP:</strong> Rp {{ number_format($dp_amount, 0, ',', '.') }}</p>
    <p><strong>Sisa:</strong> Rp {{ number_format($remaining_amount, 0, ',', '.') }}</p>
@endif
@if ($due_at)
    <p><strong>Tenggat pembayaran:</strong> {{ $due_at }}</p>
@endif
