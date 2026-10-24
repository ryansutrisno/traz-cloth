@extends('mail.layout')

@section('content')
    <h2>Pesanan siap dikirim</h2>
    <p>Halo {{ $name }}, pesanan kamu sudah siap dikirim.</p>
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Lihat detail pesanan</a></p>
@endsection
