@extends('mail.layout')

@section('content')
    <h2>Pesanan dibatalkan</h2>
    <p>Halo {{ $name }}, pesanan kamu telah dibatalkan.</p>
    @if (! empty($cancel_reason))
        <p><strong>Catatan:</strong> {{ $cancel_reason }}</p>
    @endif
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
