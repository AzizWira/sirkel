@extends('layouts.app')

@section('title', 'Penyerahan · SIRKEL')
@section('topbar', 'Penyerahan')

@section('content')
    <div class="page-head">
        <div>
            <h2>Pilih Cara Penyerahan</h2>
            <p>{{ \App\Support\SirkelUi::label($asset->preliminary_path) }} · membutuhkan
                {{ \App\Support\SirkelUi::label($initialCapability) }}</p>
        </div>
    </div>

    <div class="stepper">
        <div class="step active"><span class="dot">✓</span>Barang</div>
        <div class="step active"><span class="dot">✓</span>Kondisi</div>
        <div class="step active"><span class="dot">3</span>Rekomendasi</div>
        <div class="step active"><span class="dot">4</span>Penyerahan</div>
        <div class="step"><span class="dot">5</span>Mitra</div>
    </div>

    <form class="card form-grid" method="post" action="{{ route('user.handovers.match', $asset) }}"
        data-handover-method-form>
        @csrf
        <div class="field full">
            <label>Tujuan penyerahan *</label>
            <div class="choice-list">
                <label class="choice">
                    <input type="radio" name="handover_type" value="sale" required {{ old('handover_type') === 'sale' ? 'checked' : '' }}>
                    <span><strong>Terima penawaran nilai dari mitra</strong><br><small class="muted">Mitra dapat mengirim
                            penawaran sebelum barang diserahkan. Pembayaran dilakukan langsung dengan mitra.</small></span>
                </label>
                <label class="choice">
                    <input type="radio" name="handover_type" value="free_handover" required {{ old('handover_type') === 'free_handover' ? 'checked' : '' }}>
                    <span><strong>Serahkan tanpa kompensasi</strong><br><small class="muted">Serahkan barang tanpa penawaran
                            nilai.</small></span>
                </label>
                <label class="choice">
                    <input type="radio" name="handover_type" value="donation" required {{ old('handover_type') === 'donation' ? 'checked' : '' }}>
                    <span><strong>Donasi jika kondisi memungkinkan</strong><br><small class="muted">Jika perlu, barang dapat
                            diperiksa atau diperbaiki sebelum diteruskan untuk donasi.</small></span>
                </label>
            </div>
        </div>

        <div class="field full">
            <label>Metode serah terima *</label>
            <div class="choice-list">
                <label class="choice"><input type="radio" name="method" value="pickup" required {{ old('method', 'pickup') === 'pickup' ? 'checked' : '' }}><span><strong>Dijemput
                            Mitra</strong><br><small>Mitra akan mengambil barang di lokasi yang Anda
                            pilih.</small></span></label>
                <label class="choice"><input type="radio" name="method" value="dropoff" required {{ old('method') === 'dropoff' ? 'checked' : '' }}><span><strong>Saya Antar ke
                            Mitra</strong><br><small>Anda mengantar barang langsung ke lokasi mitra.</small></span></label>
            </div>
        </div>

        <div class="field full location-picker" data-map-link-picker data-map-id="handover-map" data-lat-id="handover-lat"
            data-lng-id="handover-lng" data-label-id="handover-location-label"
            data-resolve-url="{{ route('user.map.resolve-link') }}" data-reverse-url="{{ route('regions.reverse') }}"
            data-district-id="handover-district" data-village-id="handover-village"
            data-region-status-id="handover-region-status">
            <div class="split location-picker-head">
                <div>
                    <label data-location-title>Titik penjemputan *</label>
                    <div class="text-sm muted" data-location-help>Pilih titik penjemputan.</div>
                </div>
                <button type="button" class="btn btn-sm"
                    onclick="getMyLocation('handover-lat','handover-lng','handover-location-label','handover-map')">Ambil
                    lokasi saya</button>
            </div>

            <div class="location-source-tabs" role="tablist" aria-label="Cara memilih lokasi">
                <button type="button" class="location-source-tab active" data-location-source="map"
                    aria-selected="true">Pilih lewat peta</button>
                <button type="button" class="location-source-tab" data-location-source="link" aria-selected="false">Gunakan
                    link Google Maps</button>
            </div>

            <div data-location-panel="map">
                <div id="handover-map" class="map-box" data-picker-map data-auto-map data-lat-input="handover-lat"
                    data-lng-input="handover-lng" data-lat="{{ old('latitude', '-7.2575000') }}"
                    data-lng="{{ old('longitude', '112.7521000') }}"></div>
                <div id="handover-location-label" class="text-sm muted mt-8">Klik peta, geser pin, atau gunakan lokasi
                    perangkat.</div>
                <div class="map-link-output">
                    <div class="map-link-output-copy">
                        <span class="text-sm muted">Link titik yang dipilih</span>
                        <a id="handover-generated-map-link" data-generated-map-link href="#" target="_blank" rel="noopener"
                            class="map-link-anchor">Buka di Google Maps</a>
                    </div>
                    <button type="button" class="btn btn-sm" data-copy-map-link>Salin link</button>
                </div>
            </div>

            <div data-location-panel="link" hidden>
                <div class="map-link-import">
                    <div class="field">
                        <label for="handover-map-url">Link Google Maps</label>
                        <input id="handover-map-url" class="input" type="url" inputmode="url"
                            placeholder="Tempel link lokasi dari Google Maps" data-map-link-input>
                        <small>Bisa memakai link Bagikan Google Maps, termasuk maps.app.goo.gl. SIRKEL mengambil
                            koordinatnya lalu memindahkan pin pada peta.</small>
                    </div>
                    <button type="button" class="btn btn-primary" data-resolve-map-link>Gunakan Titik dari Link</button>
                </div>
                <div class="text-sm muted" data-map-link-status>Belum ada link yang diproses.</div>
                <div class="hint-box mt-8">Setelah titik dipindahkan dari link, pastikan Kecamatan dan Kelurahan di bawah
                    sesuai dengan lokasi penjemputan.</div>
            </div>
        </div>
        <input id="handover-lat" type="hidden" name="latitude" value="{{ old('latitude', '-7.2575000') }}">
        <input id="handover-lng" type="hidden" name="longitude" value="{{ old('longitude', '112.7521000') }}">

        <div class="field full" data-pickup-address-section>
            <label>Alamat/detail patokan penjemputan *</label>
            <textarea class="textarea" name="address" data-pickup-address
                placeholder="Contoh: rumah pagar hitam, dekat minimarket. Detail baru dibuka kepada mitra setelah permintaan diterima.">{{ old('address') }}</textarea>
            <small>Isi patokan yang membantu mitra menemukan lokasi.</small>
        </div>

        <div class="field"><label>Kecamatan *</label><select id="handover-district" class="select" name="district"
                data-searchable="true" data-search-placeholder="Cari kecamatan..." required>
                <option value="">Pilih kecamatan</option>
                @foreach($districts as $d)
                    <option value="{{ $d->name }}" {{ old('district', auth()->user()->district) === $d->name ? 'selected' : '' }}>
                        {{ $d->name }}</option>
                @endforeach
            </select></div>
        <div class="field"><label>Kelurahan *</label><select id="handover-village" class="select" name="village" required>
                <option value="">Pilih kecamatan dulu</option>
            </select></div>

        <div class="field full"><div id="handover-region-status" class="text-sm muted" aria-live="polite">Kecamatan dan kelurahan dapat dipilih manual. Saat titik peta, GPS, atau link diubah, SIRKEL akan mencoba mengisinya otomatis.</div></div>

        <div class="field"><label data-date-label>Tanggal yang diinginkan *</label><input class="input" type="date"
                name="requested_date" value="{{ old('requested_date') }}" min="{{ now()->toDateString() }}" max="{{ now()->endOfYear()->toDateString() }}" required><small>Maksimal 31 Desember {{ now()->year }}.</small></div>
        <div class="field"><label data-time-label>Rentang waktu *</label>
            <div class="cluster handover-time-range"><x-time-slot-select name="time_start" :value="old('time_start')" data-handover-time/><span>–</span><x-time-slot-select name="time_end" :value="old('time_end')" data-handover-time/></div><small>Format 24 jam, tersedia setiap 30 menit.</small>
        </div>

        <div class="field full">
            <div class="hint-box" data-method-privacy-note>
                Alamat penjemputan dibagikan setelah mitra menerima permintaan.
            </div>
        </div>

        <div class="field full"><button class="btn btn-primary" data-region-dependent-submit>Cari Mitra yang Cocok</button></div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            bindRegionSelect('handover-district', 'handover-village', @json(old('village', auth()->user()->village)));
            bindHandoverMethodForm(document.querySelector('[data-handover-method-form]'));
            bindMapLinkPicker(document.querySelector('[data-map-link-picker]'));
        });
    </script>
@endpush
