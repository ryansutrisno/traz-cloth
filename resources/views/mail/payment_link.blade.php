@extends('mail.layout')

@section('content')
    <h2>Link pelunasan tersedia</h2>
    <p>Halo {{ $name }}, silakan lakukan pelunasan pesanan kamu sebelum tenggat berikut.</p>
    @include('mail.partials.order-summary')
    <p><a href="{{ $link }}">Bayar pelunasan</a></p>
@endsection
