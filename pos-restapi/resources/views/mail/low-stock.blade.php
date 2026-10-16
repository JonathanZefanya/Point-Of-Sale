@extends('mail.layout', [
    'title' => 'Stok menipis di '.$outlet->name,
    'subtitle' => now()->locale('id')->translatedFormat('l, d F Y H:i').' · Outlet '.$outlet->code,
])

@php($td = 'padding:6px 0;border-bottom:1px solid #E4E4E0')

@section('content')
<p style="margin:0 0 12px">Produk berikut sudah habis atau di bawah stok minimum. Buat PO ke supplier atau transfer stok dari outlet lain.</p>
<table style="width:100%;border-collapse:collapse">
    <tr><th style="{{ $td }};text-align:left">Produk</th><th style="{{ $td }};text-align:right">Stok</th><th style="{{ $td }};text-align:right">Minimum</th></tr>
    @foreach ($items as $s)
        <tr>
            <td style="{{ $td }}">{{ $s['name'] }} <span style="color:#6B6B66">{{ $s['sku'] }}</span></td>
            <td style="{{ $td }};text-align:right;font-weight:600;color:{{ $s['status'] === 'out' ? '#B42318' : '#B54708' }}">{{ $s['quantity'] }} {{ $s['unit'] }}</td>
            <td style="{{ $td }};text-align:right">{{ $s['min_stock'] }}</td>
        </tr>
    @endforeach
</table>
@endsection
