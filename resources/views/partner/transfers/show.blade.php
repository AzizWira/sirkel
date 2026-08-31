@extends('layouts.app')

@section('title', 'Pengalihan ' . $asset->passport_code . ' · SIRKEL')
@section('topbar', 'Tinjau Pengalihan')

@section('content')
    <div class="page-head">
        <div>
            <div class="cluster mb-8">
                <span class="badge">{{ $asset->passport_code }}</span>
                <span
                    class="badge {{ $transfer->status === 'pending' ? 'warning' : ($transfer->status === 'received' ? 'success' : '') }}">
                    {{ $transfer->status === 'pending' ? 'Menunggu Respons Anda' : \App\Support\SirkelUi::label($transfer->status) }}
                </span>
            </div>
            <h2>{{ $asset->custom_item_name ?: $asset->category->name }}</h2>
            <p>Pengalihan dari <strong>{{ $transfer->fromPartner?->business_name ?? 'Mitra sebelumnya' }}</strong> untuk
                layanan <strong>{{ \App\Support\SirkelUi::label($transfer->required_capability) }}</strong>.</p>
        </div>
        <div class="cluster">
            <a class="btn" target="_blank" href="{{ route('passport.show', $asset->passport_code) }}">Lihat Paspor</a>
            <a class="btn" href="{{ route('partner.requests.index') }}">Kembali</a>
        </div>
    </div>

    @if($transfer->status === 'pending')
        <div class="alert warning"><strong>Konfirmasi setelah barang tiba.</strong> Setelah diterima, barang akan masuk ke menu
            Barang Ditangani.</div>
    @endif

    <div class="two-col transfer-review-grid">
        <div class="stack">
            <div class="card">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">BARANG YANG DIALIHKAN</span>
                        <h3>Ringkasan Barang</h3>
                    </div>
                </div>
                <div class="detail-grid">
                    <div><span class="metric-label">Kategori</span><strong>{{ $asset->category->name }}</strong></div>
                    <div><span
                            class="metric-label">Kelompok</span><strong>{{ $asset->category->group?->name ?? '-' }}</strong>
                    </div>
                    <div><span class="metric-label">Merek /
                            model</span><strong>{{ collect([$asset->brand, $asset->model_name])->filter()->implode(' · ') ?: '-' }}</strong>
                    </div>
                    <div><span class="metric-label">Berat
                            terverifikasi</span><strong>{{ $asset->verified_weight_kg !== null ? number_format((float) $asset->verified_weight_kg, 3, ',', '.') . ' kg' : '-' }}</strong>
                    </div>
                </div>
                @if($asset->description)
                    <div class="divider"></div>
                    <span class="metric-label">Keterangan awal warga</span>
                    <p class="mb-0">{{ $asset->description }}</p>
                @endif
                @if($asset->photos->count())
                    <div class="asset-photo-grid mt-16">
                        @foreach($asset->photos as $photo)
                            <img src="{{ asset('storage/' . $photo->path) }}"
                                alt="Foto {{ $asset->custom_item_name ?: $asset->category->name }}">
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card">
                <span class="eyebrow">ALASAN PENGALIHAN</span>
                <h3>Hasil Pemeriksaan Mitra Sebelumnya</h3>
                @if($sourceAssessment)
                    <div class="detail-grid mt-16">
                        <div><span class="metric-label">Kondisi
                                daya</span><strong>{{ \App\Support\SirkelUi::label(data_get($sourceAssessment->answers_json, 'power_status')) }}</strong>
                        </div>
                        <div><span class="metric-label">Tingkat
                                kerusakan</span><strong>{{ \App\Support\SirkelUi::label(data_get($sourceAssessment->answers_json, 'damage_level')) }}</strong>
                        </div>
                        <div><span class="metric-label">Kelayakan
                                diperbaiki</span><strong>{{ \App\Support\SirkelUi::label(data_get($sourceAssessment->answers_json, 'repair_feasible')) }}</strong>
                        </div>
                        <div><span class="metric-label">Layanan
                                lanjutan</span><strong>{{ \App\Support\SirkelUi::label($transfer->required_capability) }}</strong>
                        </div>
                    </div>
                    @if($sourceAssessment->summary)
                        <div class="notice mt-16">
                            <div><strong>Catatan pemeriksaan</strong>
                                <div class="text-sm mt-4">{{ $sourceAssessment->summary }}</div>
                            </div>
                        </div>
                    @endif
                @else
                    <p class="muted">Belum ada ringkasan pemeriksaan sebelumnya.</p>
                @endif
                @if($transfer->note)
                    <div class="divider"></div>
                    <span class="metric-label">Catatan pengalihan dari mitra asal</span>
                    <p class="mb-0">{{ $transfer->note }}</p>
                @endif
            </div>
        </div>

        <div class="stack">
            <div class="card transfer-decision-card">
                <span class="eyebrow">KEPUTUSAN MITRA ANDA</span>
                <h3>{{ $transfer->status === 'pending' ? 'Apakah barang sudah sampai dan dapat Anda tangani?' : 'Status Pengalihan' }}
                </h3>
                @if($transfer->status === 'pending')
                    <p class="muted">Butuh layanan
                        <strong>{{ \App\Support\SirkelUi::label($transfer->required_capability) }}</strong>.</p>

                    <form method="post" action="{{ route('partner.transfers.receive', $transfer) }}"
                        data-confirm="Konfirmasi bahwa barang fisik sudah benar-benar diterima di lokasi mitra Anda. Setelah ini tanggung jawab barang berpindah ke mitra Anda.">
                        @csrf
                        <button class="btn btn-primary btn-block">Konfirmasi Barang Diterima</button>
                    </form>

                    <details class="mt-16">
                        <summary class="btn btn-block">Tidak Dapat Menerima</summary>
                        <form class="stack mt-16" method="post" action="{{ route('partner.transfers.decline', $transfer) }}">
                            @csrf
                            <label class="field">
                                <span>Alasan penolakan *</span>
                                <textarea class="textarea" name="reason" required maxlength="500"
                                    placeholder="Contoh: kapasitas sedang penuh atau barang tidak dapat ditangani oleh fasilitas kami."></textarea>
                            </label>
                            <button class="btn btn-danger">Tolak Pengalihan</button>
                        </form>
                    </details>
                @else
                    <div class="notice">
                        <div>
                            <strong>{{ \App\Support\SirkelUi::label($transfer->status) }}</strong>
                            <div class="text-sm muted mt-4">
                                @if($transfer->status === 'received')
                                    Barang telah diterima pada {{ $transfer->received_at?->format('d M Y H:i') }} dan sekarang
                                    tercatat dalam Barang Ditangani.
                                @elseif($transfer->status === 'declined')
                                    Pengalihan ditolak pada {{ $transfer->declined_at?->format('d M Y H:i') }}.
                                @elseif($transfer->status === 'cancelled')
                                    Pengalihan dibatalkan oleh mitra asal.
                                @endif
                            </div>
                        </div>
                    </div>
                    @if($transfer->status === 'received')
                        <a class="btn btn-primary btn-block mt-16" href="{{ route('partner.assets.show', $asset) }}">Buka Barang
                            Ditangani</a>
                    @endif
                @endif
            </div>

        </div>
    </div>
@endsection