@extends('layouts.app')

@section('title', 'Dampak Saya · SIRKEL')

@section('topbar', 'Dampak Saya')

@section('content')
    <div class="page-head">
        <div>
            <h2>Dampak Saya</h2>
            <p>Dampak dari barang yang sudah diverifikasi mitra.</p>
        </div>
    </div>
    <div class="kpi-grid">
        <div class="kpi"><span class="metric-label">Berat
                terverifikasi</span><strong>{{ number_format($impact['verified_kg'], 3, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Berhasil
                diperbaiki</span><strong>{{ number_format($impact['repair_kg'], 3, ',', '.') }} kg</strong></div>
        <div class="kpi"><span class="metric-label">Digunakan
                kembali</span><strong>{{ number_format($impact['reuse_kg'], 3, ',', '.') }} kg</strong></div>
        <div class="kpi"><span
                class="metric-label">Didonasikan</span><strong>{{ number_format($impact['donation_kg'], 3, ',', '.') }}
                kg</strong></div>
    </div>
    <div class="card">
        <h3>Tentang perhitungan</h3>
        <p class="muted">Dampak mulai dihitung setelah berat dan hasil penanganan barang diverifikasi oleh mitra.</p>
    </div>
@endsection