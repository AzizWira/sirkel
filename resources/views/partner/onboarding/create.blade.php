@extends('layouts.app')

@section('title', 'Profil Mitra · SIRKEL')
@section('topbar', 'Profil Mitra')

@section('content')
<div class="page-head">
    <div>
        <h2>{{ $statusOnly ? 'Status Pengajuan Mitra' : ($partnerMode ? 'Profil Mitra' : ($profile ? 'Perbaiki Pengajuan Mitra' : 'Daftar sebagai Mitra')) }}
        </h2>
        <p>{{ $statusOnly ? 'Pantau hasil verifikasi tanpa kehilangan akses Warga Anda.' : 'Lengkapi lokasi, layanan, dan kategori barang yang Anda tangani.' }}
        </p>
    </div>
    @if($profile)
        <div class="cluster">
            <span
                class="badge {{ $profile->verification_status === 'approved' ? 'success' : ($profile->verification_status === 'rejected' ? 'danger' : 'warning') }}">Verifikasi:
                {{ \App\Support\SirkelUi::label($profile->verification_status) }}</span>
            @if($profile->verification_status === 'approved')
                <span class="badge {{ ($profile->admin_status ?? 'inactive') === 'active' ? 'success' : 'danger' }}">Operasional:
                    {{ \App\Support\SirkelUi::label($profile->admin_status ?? 'inactive') }}</span>
            @endif
        </div>
    @endif
</div>

@if($statusOnly)
    @if($profile->verification_status === 'pending')
        <div class="card partner-application-status">
            <span class="status-orb warning"><x-icon name="clock" /></span>
            <div>
                <span class="eyebrow">Pengajuan Mitra</span>
                <h3>Sedang menunggu verifikasi</h3>
                <p>Data pengajuan sudah diterima. Selama proses pemeriksaan, Anda tetap dapat menggunakan SIRKEL seperti biasa.
                </p>
                <div class="hint-box">Kami akan memberi notifikasi setelah pengajuan selesai diperiksa.</div>
            </div>
        </div>
    @elseif($profile->verification_status === 'approved')
        <div class="card partner-application-status">
            <span class="status-orb success"><x-icon name="check" /></span>
            <div>
                <span class="eyebrow">Pengajuan Mitra</span>
                <h3>Pengajuan Anda diterima</h3>
                <p>Pengajuan Anda sudah disetujui. Mulai login berikutnya, Anda dapat memilih masuk sebagai Warga atau Mitra.
                </p>

                @if(!$profile->approval_acknowledged_at)
                    <div class="cluster mt-16">
                        <form method="post" action="{{ route('user.become-partner.acknowledge') }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Paham</button>
                        </form>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn" type="submit">Keluar sekarang</button>
                        </form>
                    </div>
                    <p class="text-sm muted mt-8">Anda dapat membuka SIRKEL sebagai Warga maupun Mitra pada login berikutnya.</p>
                @else
                    <div class="hint-box mt-16">Akses Mitra sudah siap. Keluar lalu login kembali untuk membuka pilihan akses.</div>
                    <form method="post" action="{{ route('logout') }}" class="mt-12">
                        @csrf
                        <button class="btn btn-primary" type="submit">Keluar untuk pilih akses</button>
                    </form>
                @endif
            </div>
        </div>
    @endif
@else

@if($profile?->verification_status === 'approved')
    <div class="alert warning">Perubahan data atau layanan akan ditinjau ulang oleh admin. Selama peninjauan, permintaan
        baru dijeda.</div>
@elseif($profile?->verification_status === 'rejected')
    <div class="alert warning"><strong>Pengajuan sebelumnya belum disetujui.</strong>
        {{ $reviewNote ?: 'Periksa kembali data di bawah lalu kirim ulang setelah diperbaiki.' }}</div>
@endif

<form class="stack" method="post" enctype="multipart/form-data"
    action="{{ auth()->user()->isUser() ? route('user.become-partner.store') : route('partner.onboarding.store') }}">
    @csrf
    <div class="card">
        <h3>Identitas Mitra</h3>
        <div class="form-grid">
            <div class="field"><label>Nama usaha/komunitas *</label><input class="input" name="business_name"
                    value="{{ old('business_name', $profile?->business_name) }}" required></div>
            <div class="field"><label>Penanggung jawab *</label><input class="input" name="responsible_name"
                    value="{{ old('responsible_name', $profile?->responsible_name ?? auth()->user()->name) }}" required>
            </div>
            <div class="field"><label>WhatsApp mitra *</label><input class="input" name="phone"
                    value="{{ old('phone', $profile?->phone ?? auth()->user()->whatsapp) }}" required></div>
            <div class="field"><label>Jam operasional</label><input class="input" name="operating_hours"
                    value="{{ old('operating_hours', $profile?->operating_hours_json['display'] ?? 'Senin–Sabtu, 08.00–17.00') }}">
            </div>
            <div class="field full"><label>Alamat operasional *</label><textarea class="textarea" name="address"
                    required>{{ old('address', $profile?->address) }}</textarea></div>
            <div class="field"><label>Kecamatan *</label><select id="partner-district" class="select" name="district"
                    required>
                    <option value="">Pilih kecamatan</option>@foreach($districts as $district)
                        <option value="{{ $district->name }}" {{ old('district', $profile?->district) === $district->name ? 'selected' : '' }}>{{ $district->name }}
                    </option>@endforeach
                </select></div>
            <div class="field"><label>Kelurahan *</label><select id="partner-village" class="select" name="village"
                    required>
                    <option value="">Pilih kecamatan dulu</option>
                </select></div>
        </div>
    </div>

    <div class="card location-picker" data-map-link-picker data-map-id="partner-map" data-lat-id="partner-lat"
        data-lng-id="partner-lng" data-label-id="partner-location-label"
        data-resolve-url="{{ $partnerMode ? route('partner.map.resolve-link') : route('user.map.resolve-link') }}"
        data-reverse-url="{{ route('regions.reverse') }}" data-district-id="partner-district"
        data-village-id="partner-village" data-region-status-id="partner-region-status">
        <div class="split location-picker-head">
            <div>
                <h3>Lokasi & Radius Penjemputan</h3>
                <p class="muted mb-0">Titik ini dipakai untuk menghitung jarak dan cakupan penjemputan.</p>
            </div>
            <button type="button" class="btn"
                onclick="getMyLocation('partner-lat','partner-lng','partner-location-label','partner-map')">Ambil lokasi
                saya</button>
        </div>

        <div class="location-source-tabs" role="tablist" aria-label="Cara memilih lokasi mitra">
            <button type="button" class="location-source-tab active" data-location-source="map"
                aria-selected="true">Pilih lewat peta</button>
            <button type="button" class="location-source-tab" data-location-source="link" aria-selected="false">Gunakan
                link Google Maps</button>
        </div>

        <div data-location-panel="map">
            <div id="partner-map" class="map-box" data-picker-map data-auto-map data-lat-input="partner-lat"
                data-lng-input="partner-lng" data-lat="{{ old('latitude', $profile?->latitude ?? -7.2575) }}"
                data-lng="{{ old('longitude', $profile?->longitude ?? 112.7521) }}"></div>
            <div id="partner-location-label" class="text-sm muted mt-8">Klik peta, geser pin, atau gunakan lokasi
                perangkat.</div>
            <div class="map-link-output">
                <div class="map-link-output-copy">
                    <span class="text-sm muted">Link titik lokasi mitra</span>
                    <a data-generated-map-link href="#" target="_blank" rel="noopener" class="map-link-anchor">Buka di
                        Google Maps</a>
                </div>
                <button type="button" class="btn btn-sm" data-copy-map-link>Salin link</button>
            </div>
        </div>

        <div data-location-panel="link" hidden>
            <div class="map-link-import">
                <div class="field">
                    <label>Link Google Maps</label>
                    <input class="input" type="url" inputmode="url" placeholder="Tempel link lokasi dari Google Maps"
                        data-map-link-input>
                    <small>Bisa memakai link Bagikan Google Maps, termasuk maps.app.goo.gl. Koordinat akan dibaca lalu
                        pin pada peta dipindahkan.</small>
                </div>
                <button type="button" class="btn btn-primary" data-resolve-map-link>Gunakan Titik dari Link</button>
            </div>
            <div class="text-sm muted" data-map-link-status>Belum ada link yang diproses.</div>
        </div>

        <input id="partner-lat" type="hidden" name="latitude"
            value="{{ old('latitude', $profile?->latitude ?? -7.2575) }}">
        <input id="partner-lng" type="hidden" name="longitude"
            value="{{ old('longitude', $profile?->longitude ?? 112.7521) }}">
        <div id="partner-region-status" class="text-sm muted mt-8" aria-live="polite">Kecamatan dan kelurahan dapat dipilih manual. Saat titik lokasi berubah, SIRKEL akan mencoba mengisinya otomatis.</div>
        <div class="form-grid mt-16">
            <div class="field full"><label>Radius penjemputan (km) *</label><input class="input" type="number"
                    name="pickup_radius_km" min="1" max="100" step="0.5"
                    value="{{ old('pickup_radius_km', $profile?->pickup_radius_km ?? 10) }}" required><small>1–100 km.
                    Permintaan di luar radius tetap dapat diajukan bila mitra bersedia.</small></div>
        </div>
    </div>

    <div class="card field validation-group" data-validation-group="capabilities" data-required-group="capabilities"
        data-required-message="Pilih minimal satu layanan yang dapat dilakukan mitra Anda.">
        <h3>Layanan yang Diajukan *</h3>
        <div class="partner-cap-grid choice-group">
            @foreach($capabilities as $capability)
            @php($checked = in_array($capability->value, old('capabilities', $profile?->capabilities->pluck('capability')->all() ?? [])))
            <label class="chip-check"><input type="checkbox" name="capabilities[]" value="{{ $capability->value }}" {{ $checked ? 'checked' : '' }}><span><strong>{{ $capability->label() }}</strong></span></label>
            @endforeach
        </div>
    </div>

    <div class="card field validation-group" data-validation-group="category_ids" data-required-group="category_ids"
        data-required-message="Pilih minimal satu kategori barang yang dapat Anda terima.">
        <div class="split">
            <div>
                <h3>Kategori Barang yang Diterima *</h3>
                <p class="muted mb-0">Pilih kategori spesifik yang Anda tangani. Pilihan “Lainnya” pada suatu kelompok
                    juga menjadi cakupan umum untuk barang sejenis yang belum ada di daftar.</p>
            </div>
        </div>
        @php($accepted = old('category_ids', $profile?->acceptedCategories->pluck('id')->all() ?? []))
        <div class="partner-category-groups mt-16">
            @foreach($categories->groupBy(fn($category) => $category->group?->name ?? 'Lainnya') as $groupName => $items)
                <section class="partner-category-group">
                    <h4>{{ $groupName }}</h4>
                    <div class="partner-cap-grid choice-group">
                        @foreach($items as $category)
                            <label class="chip-check {{ $category->requiresCustomName() ? 'is-general-category' : '' }}"><input
                                    type="checkbox" name="category_ids[]" value="{{ $category->id }}" {{ in_array($category->id, $accepted) ? 'checked' : '' }}><span>{{ $category->name }}</span></label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="card">
        <h3>Dokumen Verifikasi</h3>
        <div class="form-grid">
            <div class="field"><label>KTP penanggung jawab {{ $profile?->identity_file_path ? '' : '*' }}</label>
                <div class="camera-file-picker" data-camera-file-picker><input class="input" type="file" name="ktp"
                        accept="image/jpeg,image/png,image/webp" {{ $profile?->identity_file_path ? '' : 'required' }}
                        data-camera-main-input><input type="file" accept="image/*" capture="environment" hidden
                        data-camera-capture-input>
                    <div class="cluster mt-8"><button class="btn btn-sm" type="button" data-camera-gallery>Pilih
                            Foto</button><button class="btn btn-sm" type="button" data-camera-capture><x-icon
                                name="camera" size="15" /> Kamera</button></div>
                </div><small>JPG, PNG, atau WebP · maksimal 5 MB. Hanya admin yang dapat membukanya untuk
                    verifikasi.</small>
            </div>
            <div class="field"><label>Foto tempat operasional {{ $profile?->place_photo_path ? '' : '*' }}</label>
                <div class="camera-file-picker" data-camera-file-picker><input class="input" type="file"
                        name="place_photo" accept="image/jpeg,image/png,image/webp" {{ $profile?->place_photo_path ? '' : 'required' }} data-camera-main-input><input type="file"
                        accept="image/*" capture="environment" hidden data-camera-capture-input>
                    <div class="cluster mt-8"><button class="btn btn-sm" type="button" data-camera-gallery>Pilih
                            Foto</button><button class="btn btn-sm" type="button" data-camera-capture><x-icon
                                name="camera" size="15" /> Kamera</button></div>
                </div><small>JPG, PNG, atau WebP · maksimal 5 MB.</small>
            </div>
        </div>
    </div>

    <button class="btn btn-primary">{{ $profile ? 'Kirim Perubahan' : 'Kirim Pengajuan' }}</button>
</form>
@endif
@endsection

@push('scripts')
    <script>document.addEventListener('DOMContentLoaded', () => bindRegionSelect('partner-district', 'partner-village', @json(old('village', $profile?->village))));</script>
@endpush
