@extends('mail.layout')

@section('content')
    <h2>Pesanan berhasil dibuat</h2>
    <p>Halo {{ $name }}, pesanan kamu sudah kami terima.</p>
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
