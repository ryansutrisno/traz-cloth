@extends('mail.layout')

@section('content')
    <h2>Pesanan telah dikirim</h2>
    <p>Halo {{ $name }}, pesanan kamu sedang dalam perjalanan.</p>
    @include('mail.partials.order-summary')
    @if ($resi_number)
        <p><strong>Resi:</strong> {{ $resi_number }}</p>
    @endif
    @if ($courier)
        <p><strong>Kurir:</strong> {{ $courier }}</p>
    @endif
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
