@extends('mail.layout')

@section('content')
    <h2>Pesanan telah diterima</h2>
    <p>Halo {{ $name }}, pesanan kamu telah ditandai delivered. Terima kasih sudah berbelanja.</p>
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
