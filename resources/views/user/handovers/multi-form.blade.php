@extends('layouts.app')
@section('title','Atur Penyerahan Bersama · SIRKEL')
@section('topbar','Atur Penyerahan')
@section('content')
@php
    $savedTypes = (array)($saved['handover_types'] ?? []);
    $defaultLat = old('latitude', $saved['latitude'] ?? '-7.2575000');
    $defaultLng = old('longitude', $saved['longitude'] ?? '112.7521000');
@endphp
<div class="page-head">
    <div>
        <span class="eyebrow">{{ $session->isBulk() ? 'Bulk AI' : 'Cek kondisi' }} · {{ $items->count() }} kelompok</span>
        <h2>{{ $items->count() === 1 ? 'Atur penyerahan barang' : 'Atur penyerahan sekali untuk semua' }}</h2>
        <p>Lokasi, jadwal, dan cara pengantaran cukup diisi satu kali. {{ $items->count() === 1 ? 'SIRKEL akan mencari mitra yang sesuai untuk barang ini.' : 'Tujuan tiap barang tetap dapat berbeda, lalu SIRKEL mencari apakah satu mitra dapat menangani semuanya.' }}</p>
    </div>
    <a class="btn" href="{{ route('user.intake.review',$session) }}">Kembali ke Review</a>
</div>

<div class="card stack mb-16">
    <h3>Barang dalam rencana ini</h3>
    <div class="review-grid">
        @foreach($items as $item)
            @php $asset=$item->asset; @endphp
            <div class="detail-item">
                <span class="detail-label">{{ $loop->iteration }}. {{ $asset->custom_item_name ?: $asset->category?->name }}{{ $asset->quantity>1?' ×'.$asset->quantity:'' }}</span>
                <div class="detail-value">{{ \App\Support\SirkelUi::label($asset->preliminary_path) }}</div>
            </div>
        @endforeach
    </div>
</div>

<form class="card form-grid" method="post" action="{{ route('user.intake.handover.match',$session) }}" data-handover-method-form>
    @csrf
    <div class="field full">
        <label>Cara penyerahan untuk rencana ini *</label>
        <div class="option-cards">
            <label class="option-card"><input type="radio" name="method" value="pickup" @checked(old('method',$saved['method']??'pickup')==='pickup') required><span><strong>Dijemput Mitra</strong><small>Mitra datang ke lokasi yang Anda tentukan.</small></span></label>
            <label class="option-card"><input type="radio" name="method" value="dropoff" @checked(old('method',$saved['method']??'pickup')==='dropoff') required><span><strong>Antar ke Mitra</strong><small>Anda mengantar barang ke lokasi mitra terpilih.</small></span></label>
        </div>
    </div>

    <div class="field full">
        <label>Tujuan setiap kelompok *</label>
        <small>Tujuan ini adalah pilihan Anda. Rekomendasi awal tetap menyesuaikan hasil cek kondisi dan layanan yang dimiliki mitra.</small>
        <div class="stack mt-12">
            @foreach($items as $item)
                @php
                    $asset=$item->asset;
                    $key=$asset->public_id;
                    $suggested=in_array($asset->preliminary_path,['REUSE','DONATION'],true)?'donation':'free_handover';
                    $value=old('handover_types.'.$key,$savedTypes[$key]??$suggested);
                @endphp
                <div class="card" style="padding:14px">
                    <div class="split">
                        <div><strong>{{ $asset->custom_item_name ?: $asset->category?->name }}{{ $asset->quantity>1?' ×'.$asset->quantity:'' }}</strong><div class="text-sm muted">Jalur awal: {{ \App\Support\SirkelUi::label($asset->preliminary_path) }}</div></div>
                        <select class="select" name="handover_types[{{ $key }}]" required style="max-width:320px">
                            <option value="free_handover" @selected($value==='free_handover')>Penyerahan Tanpa Kompensasi</option>
                            <option value="donation" @selected($value==='donation')>Donasi jika Kondisi Memungkinkan</option>
                            <option value="sale" @selected($value==='sale')>Dengan Penawaran Nilai</option>
                        </select>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="field full location-picker" data-map-link-picker data-map-id="multi-handover-map" data-lat-id="multi-handover-lat" data-lng-id="multi-handover-lng" data-label-id="multi-handover-location-label" data-resolve-url="{{ route('user.map.resolve-link') }}" data-reverse-url="{{ route('regions.reverse') }}" data-district-id="multi-handover-district" data-village-id="multi-handover-village" data-region-status-id="multi-handover-region-status">
        <div class="split location-picker-head">
            <div>
                <label data-location-title>Titik penjemputan *</label>
                <div class="text-sm muted" data-location-help>Pilih titik yang akan dipakai untuk rencana penyerahan ini.</div>
            </div>
            <button type="button" class="btn btn-sm" onclick="getMyLocation('multi-handover-lat','multi-handover-lng','multi-handover-location-label','multi-handover-map')">Ambil lokasi saya</button>
        </div>

        <div class="location-source-tabs" role="tablist" aria-label="Cara memilih lokasi">
            <button type="button" class="location-source-tab active" data-location-source="map" aria-selected="true">Pilih lewat peta</button>
            <button type="button" class="location-source-tab" data-location-source="link" aria-selected="false">Gunakan link Google Maps</button>
        </div>

        <div data-location-panel="map">
            <div id="multi-handover-map" class="map-box" data-picker-map data-auto-map data-lat-input="multi-handover-lat" data-lng-input="multi-handover-lng" data-lat="{{ $defaultLat }}" data-lng="{{ $defaultLng }}" data-zoom="15"></div>
            <div id="multi-handover-location-label" class="text-sm muted mt-8">Klik peta, geser pin, atau gunakan lokasi perangkat.</div>
            <div class="map-link-output">
                <div class="map-link-output-copy">
                    <span class="text-sm muted">Link titik yang dipilih</span>
                    <a id="multi-handover-generated-map-link" data-generated-map-link href="#" target="_blank" rel="noopener" class="map-link-anchor">Buka di Google Maps</a>
                </div>
                <button type="button" class="btn btn-sm" data-copy-map-link>Salin link</button>
            </div>
        </div>

        <div data-location-panel="link" hidden>
            <div class="map-link-import">
                <div class="field">
                    <label for="multi-handover-map-url">Link Google Maps</label>
                    <input id="multi-handover-map-url" class="input" type="url" inputmode="url" placeholder="Tempel link lokasi dari Google Maps" data-map-link-input>
                    <small>Bisa memakai link Bagikan Google Maps, termasuk maps.app.goo.gl. SIRKEL akan membaca koordinatnya dan memindahkan pin.</small>
                </div>
                <button type="button" class="btn btn-primary" data-resolve-map-link>Gunakan Titik dari Link</button>
            </div>
            <div class="text-sm muted" data-map-link-status>Belum ada link yang diproses.</div>
        </div>
    </div>
    <input id="multi-handover-lat" type="hidden" name="latitude" value="{{ $defaultLat }}">
    <input id="multi-handover-lng" type="hidden" name="longitude" value="{{ $defaultLng }}">

    <div class="field full" data-pickup-address-section>
        <label>Alamat/detail patokan penjemputan *</label>
        <textarea class="textarea" name="address" data-pickup-address placeholder="Contoh: rumah pagar hitam, dekat minimarket">{{ old('address',$saved['address']??'') }}</textarea>
    </div>
    <div class="field"><label>Kecamatan *</label><select id="multi-handover-district" class="select" name="district" required><option value="">Pilih kecamatan</option>@foreach($districts as $d)<option value="{{ $d->name }}" @selected(old('district',$saved['district']??auth()->user()->district)===$d->name)>{{ $d->name }}</option>@endforeach</select></div>
    <div class="field"><label>Kelurahan *</label><select id="multi-handover-village" class="select" name="village" required><option value="">Pilih kecamatan dulu</option></select></div>
    <div class="field full"><div id="multi-handover-region-status" class="text-sm muted" aria-live="polite">Kecamatan dan kelurahan tetap dapat dipilih manual. SIRKEL mencoba mengisinya saat titik lokasi berubah.</div></div>
    <div class="field"><label data-date-label>Tanggal yang diinginkan *</label><input class="input" type="date" name="requested_date" value="{{ old('requested_date',$saved['requested_date']??'') }}" min="{{ now()->toDateString() }}" max="{{ now()->endOfYear()->toDateString() }}" required><small>Maksimal 31 Desember {{ now()->year }}.</small></div>
    <div class="field"><label data-time-label>Rentang waktu</label><div class="cluster handover-time-range"><x-time-slot-select name="time_start" :value="old('time_start',$saved['time_start']??'')" data-handover-time/><span>–</span><x-time-slot-select name="time_end" :value="old('time_end',$saved['time_end']??'')" data-handover-time/></div><small>Format 24 jam setiap 30 menit; wajib untuk penjemputan.</small></div>
    <div class="field full"><button class="btn btn-primary" data-region-dependent-submit>Susun Rencana Mitra</button><small>Langkah berikutnya akan mengecek apakah ada satu mitra yang cocok untuk semua barang. Jika tidak, SIRKEL memisahkan pilihan per kelompok.</small></div>
</form>
@endsection
@push('scripts')
<script>document.addEventListener('DOMContentLoaded',()=>{bindRegionSelect('multi-handover-district','multi-handover-village',@json(old('village',$saved['village']??auth()->user()->village)));bindHandoverMethodForm(document.querySelector('[data-handover-method-form]'));bindMapLinkPicker(document.querySelector('[data-map-link-picker]'));});</script>
@endpush
