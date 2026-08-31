@extends('layouts.app')

@section('title', 'Penanganan ' . $asset->passport_code . ' · SIRKEL')
@section('topbar', 'Penanganan Barang')

@section('content')
    @php
        $caps = $profile->capabilities->where('status', 'approved')->pluck('capability')->all();
        $active = (bool) $currentCustody && !$asset->final_path;
        $needsTransfer = $active
            && in_array($asset->status, ['needs_transfer', 'transferred'], true)
            && \App\Support\SirkelUi::isTransferDecision($lastAssessment?->result_path);
        $assetName = $asset->custom_item_name ?: $asset->category->name;
        $currentOffer = $handover?->offers?->where('is_current', true)->first();
    @endphp

    <div class="page-head">
        <div>
            <div class="cluster">
                <span class="badge">{{ $asset->passport_code }}</span>
                @if($asset->final_path)
                    <span
                        class="badge {{ \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path) ? 'success' : 'warning' }}">
                        {{ \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path) ? 'Penanganan Selesai' : \App\Support\SirkelUi::label($asset->final_path) }}
                    </span>
                @elseif($pendingOutgoingTransfer)
                    <span class="badge warning">Menunggu Mitra Tujuan</span>
                @elseif($needsTransfer)
                    <span class="badge warning">Perlu Layanan Lanjutan</span>
                @elseif(!$active && $pendingIncomingTransfer)
                    <span class="badge warning">Pengalihan Masuk Menunggu</span>
                @elseif(!$active)
                    <span class="badge">Riwayat Penanganan</span>
                @else
                    <span class="badge">Sedang Ditangani</span>
                @endif
            </div>
            <h2 style="margin-top:8px">{{ $assetName }}</h2>
            <p>{{ $asset->category->name }} · {{ $asset->owner->name }}</p>
        </div>
        <div class="cluster">
            <a class="btn" target="_blank" href="{{ route('passport.show', $asset->passport_code) }}">Lihat Paspor</a>
            <a class="btn" href="{{ route('partner.assets.index') }}">Kembali</a>
        </div>
    </div>

    <div class="card flow-intent-card">
        <div class="split">
            <div>
                <div class="metric-label">Arah penyerahan warga</div>
                <h3>{{ $handoverGoalTitle }}</h3>
                <p class="muted mb-0">{{ $handoverGoalHelp }}</p>
            </div>
            <div class="cluster">
                <span class="badge">Awal: {{ \App\Support\SirkelUi::label($asset->preliminary_path, 'Belum ada') }}</span>
                <span class="badge">Penyerahan:
                    {{ \App\Support\SirkelUi::label($asset->handover_type, 'Belum dipilih') }}</span>
            </div>
        </div>
    </div>

    @if($asset->final_path)
        <div
            class="next-action-card {{ \App\Support\SirkelUi::isVerifiedOutcome($asset->final_path) ? 'success-state' : 'warning-state' }}">
            <div class="next-action-kicker">Penanganan selesai</div>
            <h3>{{ \App\Support\SirkelUi::label($asset->final_path) }}</h3>
            @if(\App\Support\SirkelUi::isVerifiedOutcome($asset->final_path))
                <p>Hasil penanganan sudah dikonfirmasi dan tercatat di Paspor SIRKEL.</p>
            @elseif($asset->final_path === 'RETURNED_TO_OWNER')
                <p>Barang sudah dikembalikan kepada warga dan tidak masuk perhitungan dampak.</p>
            @elseif($asset->final_path === 'SPLIT_TO_SUB_BATCHES')
                <p>Penanganan berlanjut pada kelompok hasil.</p>
            @else
                <p>Riwayat ditutup tanpa hasil akhir yang dapat dikonfirmasi.</p>
            @endif
        </div>
    @elseif($pendingOutgoingTransfer)
        <div class="next-action-card warning-state">
            <div class="next-action-kicker">Pengalihan sedang menunggu</div>
            <h3>Menunggu {{ $pendingOutgoingTransfer->toPartner?->business_name ?? 'mitra tujuan' }} menerima barang</h3>
            <p>Tunggu mitra tujuan mengonfirmasi penerimaan sebelum melanjutkan penanganan.</p>
            <details class="advanced-box mt-16">
                <summary>Batalkan pengalihan</summary>
                <form class="stack mt-16" method="post"
                    action="{{ route('partner.transfers.cancel', $pendingOutgoingTransfer) }}">
                    @csrf
                    <div class="field"><label>Alasan pembatalan *</label><textarea class="textarea" name="reason" required
                            maxlength="500"></textarea></div>
                    <button class="btn btn-danger">Batalkan Pengalihan</button>
                </form>
            </details>
        </div>
    @elseif($needsTransfer)
        <div class="next-action-card warning-state">
            <div class="next-action-kicker">Langkah berikutnya</div>
            <h3>{{ \App\Support\SirkelUi::label($lastAssessment?->result_path, 'Pilih layanan lanjutan') }}</h3>
            <p>Barang membutuhkan layanan lanjutan. Pilih mitra yang sesuai.</p>
            <a class="btn btn-primary" href="{{ route('partner.transfers.create', $asset) }}">Pilih Mitra Lanjutan</a>
        </div>
    @elseif($active && $asset->status === 'awaiting_donation_proof')
        <div class="next-action-card warning-state">
            <div class="next-action-kicker">Tahap akhir donasi</div>
            <h3>Salurkan barang lalu catat Bukti Donasi</h3>
            <p>Barang tetap berada dalam tanggung jawab mitra. Status <strong>Didonasikan</strong> baru diberikan setelah foto,
                waktu, dan lokasi penyaluran tercatat.</p>
        </div>
    @elseif($active)
        <div class="next-action-card">
            <div class="next-action-kicker">Langkah berikutnya</div>
            <h3>Periksa kondisi fisik dan catat apa yang benar-benar terjadi</h3>
            <p>Pilih hasil akhir jika proses sudah selesai. Jika belum, pilih layanan lanjutan yang dibutuhkan.</p>
        </div>
    @elseif($pendingIncomingTransfer)
        <div class="next-action-card warning-state">
            <div class="next-action-kicker">Ini adalah riwayat penanganan lama</div>
            <h3>Ada pengalihan baru yang meminta respons mitra Anda</h3>
            <p>Tinjau pengalihan baru dan konfirmasi setelah barang fisik tiba.</p>
            <a class="btn btn-primary" href="{{ route('partner.transfers.show', $pendingIncomingTransfer) }}">Tinjau Pengalihan
                Baru</a>
        </div>
    @else
        <div class="next-action-card">
            <div class="next-action-kicker">Riwayat penanganan</div>
            <h3>Barang sudah tidak berada dalam tanggung jawab mitra Anda</h3>
            <p>
                @if($activeCustody?->partner)
                    Barang sedang ditangani oleh <strong>{{ $activeCustody->partner->business_name }}</strong>. Halaman ini
                    menampilkan riwayat penanganan mitra Anda.
                @else
                    Halaman ini menampilkan riwayat penanganan sebelumnya.
                @endif
            </p>
        </div>
    @endif

    <div class="handling-layout mt-16">
        <div class="stack">
            <section class="card">
                <div class="split">
                    <div>
                        <h3>Ringkasan Barang</h3>
                        <p class="muted mb-0">Data barang dan berat saat diterima.</p>
                    </div>
                    <span class="badge">{{ number_format((float) ($asset->verified_weight_kg ?? 0), 3, ',', '.') }} kg</span>
                </div>
                <div class="detail-grid mt-16">
                    <div class="detail-item"><span class="detail-label">Rekomendasi awal</span>
                        <div class="detail-value">
                            {{ $asset->preliminary_path ? \App\Support\SirkelUi::label($asset->preliminary_path) : 'Belum ada' }}
                        </div>
                    </div>
                    <div class="detail-item"><span class="detail-label">Tujuan penyerahan</span>
                        <div class="detail-value">{{ \App\Support\SirkelUi::label($asset->handover_type) }}</div>
                    </div>
                    <div class="detail-item"><span class="detail-label">Merek / model</span>
                        <div class="detail-value">
                            {{ trim(($asset->brand ?? '') . ' ' . ($asset->model_name ?? '')) ?: 'Tidak diisi' }}</div>
                    </div>
                    <div class="detail-item"><span class="detail-label">Diterima mitra</span>
                        <div class="detail-value">
                            {{ $currentCustody?->received_at?->format('d M Y H:i') ?? 'Riwayat penanganan' }}</div>
                    </div>
                </div>
                @if($asset->description)
                    <div class="divider"></div>
                    <span class="detail-label">Keterangan warga</span>
                    <div>{{ $asset->description }}</div>
                @endif
                @if($asset->photos->count())
                    <div class="asset-photos mt-16">
                        @foreach($asset->photos as $photo)
                            <img src="{{ asset('storage/' . $photo->path) }}" alt="Foto barang">
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="card">
                <h3>Riwayat Pemeriksaan Mitra</h3>
                @forelse($asset->assessments->where('assessment_type', 'partner')->sortByDesc('id') as $assessment)
                    <div class="assessment-history-item">
                        <div class="split">
                            <strong>{{ $assessment->assessor?->name ?? 'Mitra' }}</strong>
                            <span
                                class="badge {{ \App\Support\SirkelUi::isTransferDecision($assessment->result_path) ? 'warning' : ($assessment->result_path && \App\Support\SirkelUi::isVerifiedOutcome($assessment->result_path) ? 'success' : '') }}">{{ \App\Support\SirkelUi::label($assessment->result_path, 'Pemeriksaan') }}</span>
                        </div>
                        <div class="text-sm muted">{{ $assessment->verified_at?->format('d M Y H:i') }}</div>
                        @if($assessment->summary)
                        <div class="mb-0 rich-ai-text" data-ai-markdown>{{ $assessment->summary }}</div>@endif
                    </div>
                @empty
                    <div class="empty">Belum ada pemeriksaan mitra.</div>
                @endforelse
            </section>
        </div>

        <div class="stack">
            @if($active && $asset->status === 'awaiting_donation_proof' && !$pendingOutgoingTransfer)
                <section class="card action-panel">
                    <div class="section-number">2</div>
                    <h3>Bukti Donasi</h3>
                    <p class="muted">Isi saat barang sudah benar-benar diserahkan kepada penerima akhir. Lokasi diambil dari
                        perangkat saat bukti dicatat.</p>
                    <form class="stack" method="post" enctype="multipart/form-data"
                        action="{{ route('partner.assets.donation-proof.store', $asset) }}" data-donation-proof-form>
                        @csrf
                        <div class="field"><label>Jenis penerima *</label><select class="select" name="recipient_type" required>
                                <option value="">Pilih penerima</option>
                                <option value="individual">Individu</option>
                                <option value="community">Komunitas</option>
                                <option value="school">Sekolah</option>
                                <option value="foundation">Yayasan / Organisasi Sosial</option>
                                <option value="other">Lainnya</option>
                            </select></div>
                        <div class="field"><label>Nama penerima / organisasi <span class="muted">(Opsional untuk
                                    individu)</span></label><input class="input" name="recipient_name"
                                placeholder="Contoh: Yayasan Pendidikan Harapan"></div>
                        <div class="field"><label>Waktu penyaluran *</label><input class="input" type="datetime-local"
                                name="donated_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                        <div class="field"><label>Foto bukti penyaluran *</label>
                            <div class="camera-file-picker" data-camera-file-picker><input class="input" type="file"
                                    name="photo" accept="image/jpeg,image/png,image/webp" required data-camera-main-input><input
                                    type="file" accept="image/*" capture="environment" hidden data-camera-capture-input>
                                <div class="cluster mt-8"><button class="btn btn-sm" type="button" data-camera-gallery>Pilih
                                        Foto</button><button class="btn btn-sm" type="button" data-camera-capture><x-icon
                                            name="camera" size="15" /> Kamera</button></div>
                            </div><small>Hindari menampilkan wajah penerima bila tidak diperlukan.</small>
                        </div>
                        <div class="field"><label>Lokasi penyaluran *</label>
                            <div class="cluster"><button class="btn" type="button" data-donation-location>Ambil Lokasi
                                    Perangkat</button><span class="text-sm muted" data-donation-location-status>Belum mengambil
                                    lokasi.</span></div><input type="hidden" name="latitude" data-donation-lat><input
                                type="hidden" name="longitude" data-donation-lng><input type="hidden" name="location_accuracy_m"
                                data-donation-accuracy><input class="input mt-8" name="location_label"
                                placeholder="Keterangan area (Opsional), contoh: Rungkut, Surabaya">
                        </div>
                        <div class="field"><label>Catatan <span class="muted">(Opsional)</span></label><textarea
                                class="textarea" name="recipient_note" maxlength="800"
                                placeholder="Contoh: perangkat sudah diuji dan diserahkan dalam kondisi dapat digunakan"></textarea>
                        </div>
                        <button class="btn btn-primary"
                            data-confirm="Pastikan barang sudah benar-benar diserahkan. Setelah bukti dicatat, outcome akan menjadi Didonasikan.">Catat
                            Bukti & Selesaikan Donasi</button>
                    </form>
                </section>
            @elseif($active && !$needsTransfer && !$pendingOutgoingTransfer)
                <section class="card action-panel">
                    <div class="section-number">1</div>
                    <h3>Catat Pemeriksaan</h3>
                    <p class="muted">Isi sesuai kondisi yang Anda periksa.</p>

                    @if($asset->handover_type === 'donation')
                        <div class="alert warning">
                            <strong>Tujuan warga: Donasi.</strong> Jika barang perlu diperbaiki lebih dulu, lanjutkan ke Guna Ulang
                            & Donasi setelah perbaikan selesai. Jika tidak layak didonasikan, pilih jalur pemulihan yang sesuai.
                        </div>
                    @endif

                    <form class="stack" method="post" action="{{ route('partner.assets.assess', $asset) }}"
                        data-partner-assessment-form
                        data-decision-options-url="{{ route('partner.assets.decision-options', $asset) }}">
                        @csrf
                        @if($partnerTemplate)
                            <div class="partner-assessment-template-head">
                                <div>
                                    <span class="eyebrow">Pemeriksaan sesuai jenis barang</span>
                                    <strong>{{ $partnerTemplate->name }}</strong>
                                </div>
                                @if($partnerTemplate->deviceCategory)
                                    <span class="badge">Khusus {{ $partnerTemplate->deviceCategory->name }}</span>
                                @elseif($partnerTemplate->deviceGroup)
                                    <span class="badge">Kelompok {{ $partnerTemplate->deviceGroup->name }}</span>
                                @else
                                    <span class="badge">Umum</span>
                                @endif
                            </div>

                            <div class="partner-assessment-questions">
                                @foreach($partnerTemplate->questions->sortBy('sort_order') as $question)
                                    @php
                                        $oldValue = old('answers.' . $question->code);
                                    @endphp
                                    <div class="field partner-assessment-question" data-partner-question="{{ $question->code }}">
                                        <label>{{ $question->text }} @if($question->required)<span
                                        aria-hidden="true">*</span>@endif</label>

                                        @if(in_array($question->type, ['single', 'boolean'], true))
                                            <select class="select" name="answers[{{ $question->code }}]" @required($question->required)>
                                                <option value="" disabled @selected($oldValue === null || $oldValue === '')>Pilih jawaban
                                                </option>
                                                @foreach($question->options as $option)
                                                    <option value="{{ $option->value }}" @selected((string) $oldValue === (string) $option->value)>
                                                        {{ $option->label }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($question->type === 'multi')
                                            <div class="option-cards compact-options">
                                                @foreach($question->options as $option)
                                                    <label class="option-card">
                                                        <input type="checkbox" name="answers[{{ $question->code }}][]"
                                                            value="{{ $option->value }}" @checked(in_array((string) $option->value, array_map('strval', (array) $oldValue), true))>
                                                        <span>{{ $option->label }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @else
                                            <textarea class="textarea" name="answers[{{ $question->code }}]" maxlength="1600"
                                                @required($question->required)
                                                placeholder="Tulis hasil pemeriksaan singkat">{{ $oldValue }}</textarea>
                                        @endif

                                        @if($question->help_text)
                                            <small class="partner-question-help">{{ $question->help_text }}</small>
                                        @endif
                                        @error('answers.' . $question->code)<small class="field-error">{{ $message }}</small>@enderror
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="alert warning">Template pemeriksaan belum tersedia untuk kategori barang ini. Hubungi admin
                                sebelum melanjutkan pemeriksaan.</div>
                        @endif
                        <div class="field">
                            <label>Apa langkah setelah pemeriksaan? *</label>
                            <p class="text-sm muted">Pilih <strong>Selesai</strong> jika penanganan sudah benar-benar selesai.
                                Jika belum, pilih layanan lanjutan.</p>
                            <div class="decision-guidance" data-decision-guidance>
                                Isi kondisi daya dan tingkat kerusakan terlebih dahulu.
                            </div>

                            <div class="decision-section-label">Belum selesai — tetap ditangani di sini</div>
                            <div class="decision-list">
                                <label class="decision-choice" data-decision-option
                                    data-decision-code="{{ $continuationOption['code'] }}">
                                    <input type="radio" name="handling_decision" value="{{ $continuationOption['code'] }}"
                                        required @checked(old('handling_decision') === $continuationOption['code'])>
                                    <span>
                                        <strong>{{ $continuationOption['label'] }}</strong>
                                        <small>{{ $continuationOption['help'] }}</small>
                                        <small class="decision-reason" data-decision-reason hidden></small>
                                    </span>
                                </label>
                            </div>

                            @if(count($completionOptions))
                                <div class="decision-section-label mt-16">Bisa diselesaikan oleh mitra ini</div>
                                <div class="decision-list">
                                    @foreach($completionOptions as $option)
                                        <label class="decision-choice" data-decision-option data-decision-code="{{ $option['code'] }}">
                                            <input type="radio" name="handling_decision" value="{{ $option['code'] }}" required
                                                @checked(old('handling_decision') === $option['code'])>
                                            <span><strong>{{ $option['label'] }}</strong><small>{{ $option['help'] }}</small><small
                                                    class="decision-reason" data-decision-reason hidden></small></span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @if(count($transferOptions))
                                <div class="decision-section-label mt-16">Belum selesai — perlu layanan lain</div>
                                <div class="decision-list">
                                    @foreach($transferOptions as $option)
                                        <label class="decision-choice warning-choice" data-decision-option
                                            data-decision-code="{{ $option['code'] }}">
                                            <input type="radio" name="handling_decision" value="{{ $option['code'] }}" required
                                                @checked(old('handling_decision') === $option['code'])>
                                            <span>
                                                <strong>{{ $option['label'] }} @if($option['recommended'])<span
                                                class="badge warning">Disarankan</span>@endif</strong>
                                                <small>{{ $option['help'] }}</small>
                                                <small class="decision-reason" data-decision-reason hidden></small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                        </div>

                        <div class="field">
                            <label>Ringkasan pemeriksaan</label>
                            <textarea class="textarea" name="summary"
                                placeholder="Catat kondisi yang ditemukan atau alasan memilih langkah berikutnya."></textarea>
                        </div>

                        @if($currentOffer && $asset->handover_type === 'sale')
                            <details class="advanced-box">
                                <summary>Nilai penyerahan setelah pemeriksaan</summary>
                                <div class="stack mt-16">
                                    <div class="field"><label>Nilai akhir (opsional)</label><input class="input" type="number"
                                            min="0" name="final_agreed_value"><small>Pembayaran dilakukan langsung dengan
                                            warga.</small></div>
                                    <div class="field"><label>Alasan jika nilai berubah</label><textarea class="textarea"
                                            name="final_value_reason"></textarea></div>
                                </div>
                            </details>
                        @endif

                        <details class="advanced-box">
                            <summary>Opsi penutupan khusus</summary>
                            <div class="mt-16">
                                <label class="decision-choice" data-decision-option
                                    data-decision-code="UNVERIFIED_FINAL_TREATMENT"><input type="radio" name="handling_decision"
                                        value="UNVERIFIED_FINAL_TREATMENT"
                                        @checked(old('handling_decision') === 'UNVERIFIED_FINAL_TREATMENT')><span><strong>Tutup
                                            tanpa hasil akhir terverifikasi</strong><small>Gunakan jika penanganan harus ditutup
                                            tetapi hasil akhirnya tidak dapat dibuktikan.</small><small class="decision-reason"
                                            data-decision-reason hidden></small></span></label>
                            </div>
                        </details>

                        <button class="btn btn-primary" @disabled(!$partnerTemplate)>Simpan Pemeriksaan</button>
                    </form>
                </section>
            @endif

            @if($needsTransfer && $active && !$pendingOutgoingTransfer)
                <section class="card action-panel">
                    <div class="section-number">2</div>
                    <h3>{{ \App\Support\SirkelUi::label($lastAssessment?->result_path, 'Alihkan Barang') }}</h3>
                    <p class="muted">Pilih mitra untuk layanan lanjutan yang dibutuhkan.</p>
                    <a class="btn btn-primary btn-block" href="{{ route('partner.transfers.create', $asset) }}">Pilih Mitra
                        Lanjutan</a>
                </section>
            @endif

            @if($asset->tracking_type === 'batch' && $active && $asset->status !== 'awaiting_donation_proof' && !$needsTransfer && !$pendingOutgoingTransfer)
                <section class="card">
                    <details>
                        <summary><strong>Kelompok barang ternyata perlu dipisah?</strong></summary>
                        <p class="muted mt-16">Gunakan jika isi kelompok ternyata memiliki kondisi berbeda.</p>
                        <form class="stack" method="post" action="{{ route('partner.assets.split', $asset) }}">
                            @csrf
                            @for($i = 0; $i < 2; $i++)
                                <div class="form-grid">
                                    <div class="field"><label>Kelompok {{ $i + 1 }} — jumlah</label><input class="input"
                                            type="number" min="1" name="parts[{{ $i }}][quantity]" required></div>
                                    <div class="field"><label>Berat (kg)</label><input class="input" type="number" min="0.001"
                                            step="0.001" name="parts[{{ $i }}][verified_weight_kg]" required></div>
                                    <div class="field full"><label>Keterangan kondisi</label><input class="input"
                                            name="parts[{{ $i }}][condition_class]" required></div>
                                </div>
                            @endfor
                            <button class="btn">Pisahkan Menjadi 2 Kelompok</button>
                        </form>
                    </details>
                </section>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-donation-proof-form]'); if (!form) return;
            const button = form.querySelector('[data-donation-location]'); const status = form.querySelector('[data-donation-location-status]');
            button?.addEventListener('click', () => {
                if (!navigator.geolocation) { status.textContent = 'Geolocation tidak didukung perangkat ini.'; return; }
                status.textContent = 'Mengambil lokasi...';
                navigator.geolocation.getCurrentPosition(pos => {
                    form.querySelector('[data-donation-lat]').value = pos.coords.latitude.toFixed(7);
                    form.querySelector('[data-donation-lng]').value = pos.coords.longitude.toFixed(7);
                    form.querySelector('[data-donation-accuracy]').value = Math.round(pos.coords.accuracy || 0);
                    status.textContent = `Lokasi tersimpan · akurasi ±${Math.round(pos.coords.accuracy || 0)} m`;
                }, () => { status.textContent = 'Lokasi gagal diambil. Izinkan akses lokasi lalu coba lagi.'; }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            });
        });
    </script>
@endpush