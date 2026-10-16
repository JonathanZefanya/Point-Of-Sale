@extends('mail.layout', [
    'title' => "Laporan harian {$outlet->name}",
    'subtitle' => \Carbon\CarbonImmutable::parse($date)->locale('id')->translatedFormat('l, d F Y').' · Outlet '.$outlet->code,
])

@php
    $rp = fn ($v) => 'Rp'.number_format((int) $v, 0, ',', '.');
    $td = 'padding:6px 0;border-bottom:1px solid #E4E4E0';
    $m = $data['metrics'];
@endphp

@section('content')
<table style="width:100%;border-collapse:collapse;margin-bottom:16px">
    <tr><td style="{{ $td }}">Penjualan bersih</td><td style="{{ $td }};text-align:right;font-weight:600">{{ $rp($m['revenue']) }}</td></tr>
    <tr><td style="{{ $td }}">Jumlah transaksi</td><td style="{{ $td }};text-align:right">{{ number_format($m['transactions'], 0, ',', '.') }}</td></tr>
    <tr><td style="{{ $td }}">Rata-rata per transaksi</td><td style="{{ $td }};text-align:right">{{ $rp($m['average']) }}</td></tr>
    <tr><td style="{{ $td }}">Laba kotor</td><td style="{{ $td }};text-align:right">{{ $rp($data['gross_profit']) }}</td></tr>
</table>

<h2 style="font-size:15px;margin:0 0 6px">Per metode bayar</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:16px">
    @forelse ($data['payments'] as $p)
        <tr><td style="{{ $td }}">{{ $p['label'] }} ({{ $p['transactions'] }})</td><td style="{{ $td }};text-align:right">{{ $rp($p['amount']) }}</td></tr>
    @empty
        <tr><td style="{{ $td }};color:#6B6B66">Tidak ada pembayaran.</td></tr>
    @endforelse
</table>

<h2 style="font-size:15px;margin:0 0 6px">Produk terlaris</h2>
<table style="width:100%;border-collapse:collapse;margin-bottom:16px">
    @forelse ($data['top_products'] as $p)
        <tr><td style="{{ $td }}">{{ $p['name'] }}</td><td style="{{ $td }};text-align:right">{{ $p['quantity'] }} terjual · {{ $rp($p['revenue']) }}</td></tr>
    @empty
        <tr><td style="{{ $td }};color:#6B6B66">Belum ada penjualan.</td></tr>
    @endforelse
</table>

@if ($data['low_stock'])
    <h2 style="font-size:15px;margin:0 0 6px;color:#B54708">Stok perlu diisi ulang</h2>
    <table style="width:100%;border-collapse:collapse">
        @foreach ($data['low_stock'] as $s)
            <tr><td style="{{ $td }}">{{ $s['name'] }}</td><td style="{{ $td }};text-align:right;color:{{ $s['status'] === 'out' ? '#B42318' : '#B54708' }}">{{ $s['quantity'] }} {{ $s['unit'] }} (min. {{ $s['min_stock'] }})</td></tr>
        @endforeach
    </table>
@endif
@endsection
