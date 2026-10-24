@extends('mail.layout')

@section('content')
    <h2>Pembayaran diterima</h2>
    <p>Halo {{ $name }}, {{ $payment_label ?? 'Pembayaran' }} untuk pesanan kamu sudah kami terima.</p>
    @if (! empty($paid_amount))
        <p><strong>Jumlah diterima:</strong> Rp {{ number_format($paid_amount, 0, ',', '.') }}</p>
    @endif
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
