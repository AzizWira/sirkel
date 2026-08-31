@extends('layouts.app')

@section('title', 'Kategori & Cek Kondisi · SIRKEL')
@section('topbar', 'Kategori & Cek Kondisi')

@section('content')
    @php
        $fieldMap = $ruleFields->keyBy('code');
        $conditionText = function (array $conditions) use ($fieldMap) {
            return collect($conditions)->map(function ($value, $field) use ($fieldMap) {
                $meta = $fieldMap->get($field);
                $fieldLabel = $meta['label'] ?? \App\Support\SirkelUi::label($field);
                $values = is_array($value) ? $value : [$value];
                $valueLabel = collect($values)->map(function ($item) use ($meta) {
                    $option = collect($meta['options'] ?? [])->firstWhere('value', (string) $item);
                    return $option['label'] ?? \App\Support\SirkelUi::label($item);
                })->implode(' ATAU ');
                return $fieldLabel . ' = ' . $valueLabel;
            })->implode(' DAN ');
        };
        $conditionEditorValue = function ($value) {
            if (is_array($value))
                return (string) ($value[0] ?? '');
            return (string) ($value ?? '');
        };
        $allCategories = $groups->flatMap->categories;
        $templateScope = function ($tpl) {
            if ($tpl->deviceCategory)
                return 'Kategori: ' . $tpl->deviceCategory->name;
            if ($tpl->deviceGroup)
                return 'Kelompok: ' . $tpl->deviceGroup->name;
            return 'Berlaku umum';
        };
    @endphp

    <div class="page-head">
        <div>
            <h2>Kategori & Cek Kondisi</h2>
            <p>Kelola kelompok elektronik, kategori, pertanyaan, dan aturan yang digunakan SIRKEL.</p>
        </div>
    </div>

    <div class="master-overview-grid mt-16">
        <div class="card master-overview-card">
            <span>Kelompok</span><strong>{{ $groups->count() }}</strong><small>{{ $groups->where('active', true)->count() }}
                aktif</small></div>
        <div class="card master-overview-card">
            <span>Kategori</span><strong>{{ $allCategories->count() }}</strong><small>{{ $allCategories->where('active', true)->count() }}
                aktif</small></div>
        <div class="card master-overview-card"><span>Cek kondisi
                warga</span><strong>{{ $citizenTemplates->count() }}</strong><small>template</small></div>
        <div class="card master-overview-card"><span>Pemeriksaan
                mitra</span><strong>{{ $partnerTemplates->count() }}</strong><small>template</small></div>
    </div>

    <div class="master-workspace mt-16" data-master-tabs>
        <div class="master-tabbar" role="tablist" aria-label="Bagian master data">
            <button type="button" class="master-tab active" data-master-tab="groups">Kelompok</button>
            <button type="button" class="master-tab" data-master-tab="categories">Kategori</button>
            <button type="button" class="master-tab" data-master-tab="citizen">Cek Kondisi Warga</button>
            <button type="button" class="master-tab" data-master-tab="partner">Pemeriksaan Mitra</button>
            <button type="button" class="master-tab" data-master-tab="rules">Aturan Rekomendasi</button>
        </div>

        <section class="master-panel" data-master-panel="groups">
            <div class="master-panel-head">
                <div>
                    <h3>Kelompok Elektronik</h3>
                    <p class="muted mb-0">Kelompok membantu mengatur kategori dan pertanyaan yang digunakan untuk jenis
                        barang terkait.</p>
                </div>
                <button type="button" class="btn btn-primary" data-toggle-target="create-group">+ Tambah Kelompok</button>
            </div>

            <div id="create-group" class="admin-inline-editor mt-16" hidden>
                <form class="form-grid" method="post" action="{{ route('admin.master.group') }}">@csrf
                    <div class="field"><label>Nama kelompok *</label><input class="input" name="name"
                            placeholder="Contoh: Perangkat Energi Rumah" required></div>
                    <div class="field"><label>Urutan</label><input class="input" type="number" name="sort_order" min="1"
                            placeholder="Otomatis"></div>
                    <div class="field full"><label>Deskripsi</label><textarea class="textarea" name="description" rows="2"
                            placeholder="Jelaskan jenis barang yang termasuk kelompok ini."></textarea></div>
                    <label class="chip-check"><input type="checkbox" name="active" value="1" checked> Aktif</label>
                    <details class="field full advanced-box">
                        <summary>Pengaturan lanjutan</summary>
                        <div class="field mt-16"><label>ID internal</label><input class="input" name="code"
                                placeholder="Dibuat otomatis jika kosong"></div>
                    </details>
                    <div class="field full cluster"><button class="btn btn-primary">Simpan Kelompok</button><button
                            type="button" class="btn" data-close-target="create-group">Batal</button></div>
                </form>
            </div>

            <div class="master-card-grid mt-16">
                @foreach($groups as $group)
                    <article class="card master-item-card {{ $group->active ? '' : 'is-inactive' }}">
                        <div class="split">
                            <div>
                                <h4>{{ $group->name }}</h4>
                                <p class="muted mb-0">{{ $group->description ?: 'Belum ada deskripsi.' }}</p>
                            </div>
                            <span
                                class="badge {{ $group->active ? 'success' : '' }}">{{ $group->active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <div class="cluster mt-16"><span class="tag">{{ $group->categories->count() }} kategori</span><span
                                class="tag">Urutan {{ $group->sort_order }}</span></div>
                        <details class="admin-edit-details mt-16">
                            <summary>Edit kelompok</summary>
                            <form class="form-grid mt-16" method="post" action="{{ route('admin.master.group', $group) }}">@csrf
                                <div class="field"><label>Nama kelompok *</label><input class="input" name="name"
                                        value="{{ $group->name }}" required></div>
                                <div class="field"><label>Urutan *</label><input class="input" type="number" name="sort_order"
                                        min="1" value="{{ $group->sort_order }}" required></div>
                                <div class="field full"><label>Deskripsi</label><textarea class="textarea" name="description"
                                        rows="2">{{ $group->description }}</textarea></div>
                                <label class="chip-check"><input type="checkbox" name="active" value="1"
                                        @checked($group->active)> Aktif</label>
                                <details class="field full advanced-box">
                                    <summary>Kode sistem</summary>
                                    <div class="field mt-16"><input class="input" name="code" value="{{ $group->code }}"></div>
                                </details>
                                <div class="field full"><button class="btn">Simpan Perubahan</button></div>
                            </form>
                        </details>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="master-panel" data-master-panel="categories" hidden>
            <div class="master-panel-head">
                <div>
                    <h3>Kategori Barang</h3>
                    <p class="muted mb-0">Cari kategori, lihat kelompoknya, lalu edit tanpa membuka tabel panjang.</p>
                </div>
                <button type="button" class="btn btn-primary" data-toggle-target="create-category">+ Tambah
                    Kategori</button>
            </div>

            <div id="create-category" class="admin-inline-editor mt-16" hidden>
                <form class="form-grid" method="post" action="{{ route('admin.master.category') }}">@csrf
                    <div class="field"><label>Kelompok *</label><select class="select" name="device_group_id"
                            data-searchable="true" data-search-placeholder="Cari kelompok..."
                            required>@foreach($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                        </select></div>
                    <div class="field"><label>Nama kategori *</label><input class="input" name="name"
                            placeholder="Contoh: Mesin Jahit Elektrik" required></div>
                    <div class="field"><label>Urutan</label><input class="input" type="number" name="sort_order" min="1"
                            placeholder="Otomatis"></div>
                    <div class="field full">
                        <div class="cluster"><label class="chip-check"><input type="checkbox" name="supports_batch"
                                    value="1"> Bisa didaftarkan berkelompok</label><label class="chip-check"><input
                                    type="checkbox" name="special_handling_possible" value="1"> Bisa perlu penanganan
                                khusus</label><label class="chip-check"><input type="checkbox" name="active" value="1"
                                    checked> Aktif</label></div>
                    </div>
                    <details class="field full advanced-box">
                        <summary>Pengaturan lanjutan</summary>
                        <div class="field mt-16"><label>ID internal</label><input class="input" name="code"
                                placeholder="Dibuat otomatis jika kosong"></div>
                    </details>
                    <div class="field full cluster"><button class="btn btn-primary">Simpan Kategori</button><button
                            type="button" class="btn" data-close-target="create-category">Batal</button></div>
                </form>
            </div>

            <div class="master-filterbar mt-16">
                <input class="input" type="search" placeholder="Cari kulkas, laptop, kabel..." data-master-category-search>
                <select class="select" data-master-category-group data-searchable="true"
                    data-search-placeholder="Cari kelompok...">
                    <option value="">Semua kelompok</option>@foreach($groups as $g)
                    <option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
            </div>

            <div class="master-category-list mt-16">
                @foreach($groups as $group)
                    @foreach($group->categories as $category)
                        <article class="card master-category-row {{ $category->active ? '' : 'is-inactive' }}"
                            data-master-category-card data-group-id="{{ $group->id }}"
                            data-search-text="{{ strtolower($group->name . ' ' . $category->name . ' ' . $category->code) }}">
                            <div class="master-category-main">
                                <div class="cluster"><strong>{{ $category->name }}</strong><span
                                        class="badge">{{ $group->name }}</span>@if(!$category->active)<span
                                        class="badge">Nonaktif</span>@endif</div>
                                <div class="cluster mt-8"><span
                                        class="text-sm muted">{{ $category->supports_batch ? 'Bisa kelompok barang' : 'Satuan' }}</span>@if($category->special_handling_possible)<span
                                        class="text-sm" style="color:var(--warning)">Bisa perlu penanganan khusus</span>@endif</div>
                            </div>
                            <details class="admin-edit-details">
                                <summary>Edit</summary>
                                <form class="form-grid mt-16" method="post" action="{{ route('admin.master.category', $category) }}">
                                    @csrf
                                    <div class="field"><label>Kelompok *</label><select class="select" name="device_group_id"
                                            data-searchable="true" required>@foreach($groups as $g)
                                                <option value="{{ $g->id }}" @selected($category->device_group_id === $g->id)>
                                            {{ $g->name }}</option>@endforeach
                                        </select></div>
                                    <div class="field"><label>Nama kategori *</label><input class="input" name="name"
                                            value="{{ $category->name }}" required></div>
                                    <div class="field"><label>Urutan *</label><input class="input" type="number" name="sort_order"
                                            min="1" value="{{ $category->sort_order }}" required></div>
                                    <div class="field full">
                                        <div class="cluster"><label class="chip-check"><input type="checkbox" name="supports_batch"
                                                    value="1" @checked($category->supports_batch)> Bisa kelompok
                                                barang</label><label class="chip-check"><input type="checkbox"
                                                    name="special_handling_possible" value="1"
                                                    @checked($category->special_handling_possible)> Bisa perlu penanganan
                                                khusus</label><label class="chip-check"><input type="checkbox" name="active"
                                                    value="1" @checked($category->active)> Aktif</label></div>
                                    </div>
                                    <details class="field full advanced-box">
                                        <summary>Kode sistem</summary>
                                        <div class="field mt-16"><input class="input" name="code" value="{{ $category->code }}">
                                        </div>
                                    </details>
                                    <div class="field full"><button class="btn">Simpan Perubahan</button></div>
                                </form>
                            </details>
                        </article>
                    @endforeach
                @endforeach
                <div class="empty" data-master-category-empty hidden>Tidak ada kategori yang sesuai pencarian.</div>
            </div>
        </section>

        @foreach([['citizen', 'Cek Kondisi Warga', $citizenTemplates, 'Pertanyaan awal yang dijawab warga sebelum memilih cara penyerahan.'], ['partner', 'Pemeriksaan Mitra', $partnerTemplates, 'Pertanyaan pemeriksaan fisik setelah barang benar-benar diterima mitra.']] as [$audience, $title, $audienceTemplates, $description])
            <section class="master-panel" data-master-panel="{{ $audience }}" hidden>
                <div class="master-panel-head">
                    <div>
                        <h3>{{ $title }}</h3>
                        <p class="muted mb-0">{{ $description }}</p>
                    </div>
                    <button type="button" class="btn btn-primary" data-toggle-target="create-template-{{ $audience }}">+ Tambah
                        Template</button>
                </div>

                <div class="master-callout mt-16">
                    @if($audience === 'citizen')
                        <strong>Urutan cakupan:</strong> kategori spesifik → kelompok → template umum. Aturan rekomendasi hanya
                        membaca pertanyaan warga dengan jawaban terstruktur.
                    @else
                        <strong>Urutan cakupan:</strong> kategori spesifik → kelompok → template umum. Pertanyaan tambahan boleh
                        spesifik perangkat, sedangkan safety dan validasi outcome tetap dijaga sistem.
                    @endif
                </div>

                <div id="create-template-{{ $audience }}" class="admin-inline-editor mt-16" hidden>
                    <form class="form-grid" method="post" action="{{ route('admin.master.template') }}">@csrf
                        <input type="hidden" name="audience" value="{{ $audience }}">
                        <div class="field"><label>Nama template *</label><input class="input" name="name"
                                placeholder="Contoh: {{ $audience === 'citizen' ? 'Cek Kondisi Peralatan Pendingin' : 'Pemeriksaan Mitra Peralatan Pendingin' }}"
                                required></div>
                        <div class="field"><label>Cakupan</label><select class="select" name="scope_type"
                                data-template-scope-select>
                                <option value="general">Berlaku umum</option>
                                <option value="group">Satu kelompok</option>
                                <option value="category">Satu kategori</option>
                            </select></div>
                        <div class="field" data-template-scope-field="group" hidden><label>Kelompok</label><select
                                class="select" name="device_group_id" data-searchable="true">
                                <option value="">Pilih kelompok</option>@foreach($groups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                            </select></div>
                        <div class="field" data-template-scope-field="category" hidden><label>Kategori</label><select
                                class="select" name="device_category_id" data-searchable="true"
                                data-search-placeholder="Cari kategori...">
                                <option value="">Pilih kategori</option>@foreach($groups as $g)
                                    <optgroup label="{{ $g->name }}">@foreach($g->categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                </optgroup>@endforeach
                            </select></div>
                        <label class="chip-check"><input type="checkbox" name="active" value="1" checked> Aktif</label>
                        <details class="field full advanced-box">
                            <summary>Pengaturan lanjutan</summary>
                            <div class="field mt-16"><label>ID internal template</label><input class="input" name="code"
                                    placeholder="Dibuat otomatis jika kosong"></div>
                        </details>
                        <div class="field full cluster"><button class="btn btn-primary">Simpan Template</button><button
                                type="button" class="btn" data-close-target="create-template-{{ $audience }}">Batal</button>
                        </div>
                    </form>
                </div>

                <div class="stack mt-16">
                    @forelse($audienceTemplates as $tpl)
                        <article class="card master-template-card {{ $tpl->active ? '' : 'is-inactive' }}">
                            <div class="split master-template-head">
                                <div>
                                    <div class="cluster">
                                        <h4>{{ $tpl->name }}</h4><span
                                            class="badge">{{ $templateScope($tpl) }}</span>@if(!$tpl->active)<span
                                            class="badge">Nonaktif</span>@endif
                                    </div>
                                    <p class="muted mb-0">{{ $tpl->questions->count() }} pertanyaan</p>
                                </div>
                                <details class="admin-edit-details">
                                    <summary>Edit template</summary>
                                    <form class="form-grid mt-16" method="post" action="{{ route('admin.master.template') }}"
                                        data-template-edit-scope>@csrf
                                        <input type="hidden" name="template_id" value="{{ $tpl->id }}"><input type="hidden"
                                            name="audience" value="{{ $audience }}">
                                        <div class="field"><label>Nama *</label><input class="input" name="name"
                                                value="{{ $tpl->name }}" required></div>
                                        <div class="field"><label>Kelompok</label><select class="select" name="device_group_id"
                                                data-searchable="true">
                                                <option value="">Tidak khusus kelompok</option>@foreach($groups as $g)
                                                    <option value="{{ $g->id }}" @selected($tpl->device_group_id === $g->id)>
                                                {{ $g->name }}</option>@endforeach
                                            </select></div>
                                        <div class="field"><label>Kategori</label><select class="select" name="device_category_id"
                                                data-searchable="true">
                                                <option value="">Tidak khusus kategori</option>@foreach($groups as $g)
                                                    <optgroup label="{{ $g->name }}">@foreach($g->categories as $c)
                                                        <option value="{{ $c->id }}" @selected($tpl->device_category_id === $c->id)>
                                                    {{ $c->name }}</option>@endforeach
                                                </optgroup>@endforeach
                                            </select></div>
                                        <label class="chip-check"><input type="checkbox" name="active" value="1"
                                                @checked($tpl->active)> Aktif</label>
                                        <details class="field full advanced-box">
                                            <summary>Kode sistem</summary>
                                            <div class="field mt-16"><input class="input" name="code" value="{{ $tpl->code }}">
                                            </div>
                                        </details>
                                        <div class="field full"><button class="btn">Simpan Template</button></div>
                                    </form>
                                </details>
                            </div>

                            <div class="question-admin-list mt-16">
                                @foreach($tpl->questions as $q)
                                    <details class="question-admin-item">
                                        <summary>
                                            <span class="question-order">{{ $q->sort_order }}</span>
                                            <span
                                                class="question-summary-copy"><strong>{{ $q->text }}</strong><small>{{ \App\Support\SirkelUi::label($q->type) }}{{ $q->required ? ' · wajib' : ' · opsional' }}</small></span>
                                            <span class="question-edit-label">Edit</span>
                                        </summary>
                                        <form class="form-grid question-editor mt-16" method="post"
                                            action="{{ route('admin.master.question', $tpl) }}" data-question-editor>@csrf
                                            <input type="hidden" name="question_id" value="{{ $q->id }}">
                                            <div class="field full"><label>Pertanyaan *</label><input class="input" name="text"
                                                    value="{{ $q->text }}" required></div>
                                            <div class="field"><label>Jenis jawaban *</label><select class="select" name="type"
                                                    data-question-type>
                                                    <option value="single" @selected($q->type === 'single')>Pilih satu jawaban</option>
                                                    <option value="multi" @selected($q->type === 'multi')>Boleh pilih beberapa</option>
                                                    <option value="boolean" @selected($q->type === 'boolean')>Ya / Tidak</option>
                                                    <option value="text" @selected($q->type === 'text')>Jawaban teks</option>
                                                </select></div>
                                            <div class="field"><label>Urutan *</label><input class="input" type="number"
                                                    name="sort_order" min="1" value="{{ $q->sort_order }}" required></div>
                                            <div class="field full"><label>Penjelasan biasa</label><textarea class="textarea"
                                                    name="help_text" rows="3"
                                                    placeholder="Panduan singkat yang tampil tanpa AI">{{ $q->help_text }}</textarea><small>Teks
                                                    ini tampil di popup Bantuan Pertanyaan pada halaman Cek Kondisi warga.</small></div>
                                            <div class="field full option-editor" data-option-editor
                                                @if(!in_array($q->type, ['single', 'multi'])) hidden @endif>
                                                <div class="split">
                                                    <div><label>Pilihan jawaban</label><small>Tambah atau ubah satu per satu. Tidak
                                                            perlu menulis satu pilihan per baris.</small></div><button type="button"
                                                        class="btn btn-sm" data-add-option>+ Tambah Pilihan</button>
                                                </div>
                                                <div class="option-editor-list mt-8" data-option-list>
                                                    @foreach($q->options as $option)
                                                        <div class="option-editor-row" data-option-row><input class="input"
                                                                name="option_labels[]" value="{{ $option->label }}"
                                                                aria-label="Label pilihan"><input type="hidden" name="option_values[]"
                                                                value="{{ $option->value }}"><button type="button" class="btn btn-sm"
                                                                data-remove-option>Hapus</button></div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <label class="chip-check"><input type="checkbox" name="required" value="1"
                                                    @checked($q->required)> Wajib dijawab</label>
                                            <details class="field full advanced-box">
                                                <summary>Pengaturan lanjutan</summary>
                                                <div class="field mt-16"><label>ID internal pertanyaan</label><input class="input"
                                                        name="code" value="{{ $q->code }}"></div>
                                            </details>
                                            <div class="field full"><button class="btn">Simpan Pertanyaan</button></div>
                                        </form>
                                    </details>
                                @endforeach
                            </div>

                            <details class="admin-create-box mt-16">
                                <summary>+ Tambah pertanyaan</summary>
                                <form class="form-grid question-editor mt-16" method="post"
                                    action="{{ route('admin.master.question', $tpl) }}" data-question-editor>@csrf
                                    <div class="field full"><label>Pertanyaan *</label><input class="input" name="text"
                                            placeholder="Tuliskan pertanyaan dengan bahasa yang mudah dipahami" required></div>
                                    <div class="field"><label>Jenis jawaban *</label><select class="select" name="type"
                                            data-question-type>
                                            <option value="single">Pilih satu jawaban</option>
                                            <option value="multi">Boleh pilih beberapa</option>
                                            <option value="boolean">Ya / Tidak</option>
                                            <option value="text">Jawaban teks</option>
                                        </select></div>
                                    <div class="field"><label>Urutan</label><input class="input" type="number" name="sort_order"
                                            min="1" placeholder="Otomatis"></div>
                                    <div class="field full"><label>Penjelasan biasa</label><textarea class="textarea"
                                            name="help_text" rows="3"
                                            placeholder="Contoh kondisi atau arti pilihan agar pengguna tidak bingung"></textarea>
                                    </div>
                                    <div class="field full option-editor" data-option-editor>
                                        <div class="split">
                                            <div><label>Pilihan jawaban</label><small>Minimal dua pilihan untuk jenis Pilih
                                                    Satu/Pilih Beberapa.</small></div><button type="button" class="btn btn-sm"
                                                data-add-option>+ Tambah Pilihan</button>
                                        </div>
                                        <div class="option-editor-list mt-8" data-option-list>
                                            <div class="option-editor-row" data-option-row><input class="input"
                                                    name="option_labels[]" placeholder="Pilihan 1"><input type="hidden"
                                                    name="option_values[]"><button type="button" class="btn btn-sm"
                                                    data-remove-option>Hapus</button></div>
                                            <div class="option-editor-row" data-option-row><input class="input"
                                                    name="option_labels[]" placeholder="Pilihan 2"><input type="hidden"
                                                    name="option_values[]"><button type="button" class="btn btn-sm"
                                                    data-remove-option>Hapus</button></div>
                                        </div>
                                    </div>
                                    <label class="chip-check"><input type="checkbox" name="required" value="1" checked> Wajib
                                        dijawab</label>
                                    <details class="field full advanced-box">
                                        <summary>Pengaturan lanjutan</summary>
                                        <div class="field mt-16"><label>ID internal pertanyaan</label><input class="input"
                                                name="code" placeholder="Dibuat otomatis jika kosong"></div>
                                    </details>
                                    <div class="field full"><button class="btn btn-primary">Simpan Pertanyaan</button></div>
                                </form>
                            </details>
                        </article>
                    @empty
                        <div class="empty">Belum ada template pada bagian ini.</div>
                    @endforelse
                </div>
            </section>
        @endforeach

        <section class="master-panel" data-master-panel="rules" hidden>
            <div class="master-panel-head">
                <div>
                    <h3>Aturan Rekomendasi Warga</h3>
                    <p class="muted mb-0">Aturan dengan prioritas lebih kecil diperiksa lebih dahulu. Aturan keselamatan
                        utama tetap berlaku dan tidak dapat dilemahkan dari halaman ini.</p>
                </div>
            </div>
            <div class="stack mt-16">
                @foreach($rules as $r)
                    <details class="card rule-readable-row-wrap">
                        <summary class="rule-readable-row"><span class="rule-order">{{ $r->priority }}</span>
                            <div><strong>{{ $r->name }}</strong>
                                <div class="text-sm">Jika <strong>{{ $conditionText($r->conditions_json ?? []) }}</strong></div>
                                <div class="text-sm muted">Maka:
                                    <strong>{{ \App\Support\SirkelUi::label($r->result_path) }}</strong></div>
                            </div><span class="badge {{ $r->active ? 'success' : '' }}">{{ $r->active ? 'Aktif' : 'Nonaktif' }}</span>
                        </summary>
                        <form class="form-grid mt-16" method="post" action="{{ route('admin.master.rule', $r) }}"
                            data-rule-builder>@csrf
                            <div class="field full"><label>Nama aturan *</label><input class="input" name="name"
                                    value="{{ $r->name }}" required></div>
                            @php $conds = collect($r->conditions_json ?? []);
                            $keys = $conds->keys()->values(); @endphp
                            <div class="field"><label>Jika jawaban pada *</label><select class="select" name="condition_field_1"
                                    data-rule-field="1" required>
                                    <option value="">Pilih pertanyaan</option>@foreach($ruleFields as $f)
                                        <option value="{{ $f['code'] }}" @selected(($keys[0] ?? null) === $f['code'])>{{ $f['label'] }}
                                    </option>@endforeach
                                </select></div>
                            <div class="field"><label>Jawabannya *</label><select class="select" name="condition_value_1"
                                    data-rule-value="1"
                                    data-selected-value="{{ $keys->count() ? $conditionEditorValue($conds[$keys[0]] ?? '') : '' }}"
                                    required>
                                    <option value="">Pilih pertanyaan dulu</option>
                                </select></div>
                            <div class="field"><label>Syarat kedua</label><select class="select" name="condition_field_2"
                                    data-rule-field="2">
                                    <option value="">Tidak ada</option>@foreach($ruleFields as $f)
                                        <option value="{{ $f['code'] }}" @selected(($keys[1] ?? null) === $f['code'])>{{ $f['label'] }}
                                    </option>@endforeach
                                </select></div>
                            <div class="field"><label>Jawabannya</label><select class="select" name="condition_value_2"
                                    data-rule-value="2"
                                    data-selected-value="{{ $keys->count() > 1 ? $conditionEditorValue($conds[$keys[1]] ?? '') : '' }}">
                                    <option value="">-</option>
                                </select></div>
                            <div class="field"><label>Syarat ketiga</label><select class="select" name="condition_field_3"
                                    data-rule-field="3">
                                    <option value="">Tidak ada</option>@foreach($ruleFields as $f)
                                        <option value="{{ $f['code'] }}" @selected(($keys[2] ?? null) === $f['code'])>{{ $f['label'] }}
                                    </option>@endforeach
                                </select></div>
                            <div class="field"><label>Jawabannya</label><select class="select" name="condition_value_3"
                                    data-rule-value="3"
                                    data-selected-value="{{ $keys->count() > 2 ? $conditionEditorValue($conds[$keys[2]] ?? '') : '' }}">
                                    <option value="">-</option>
                                </select></div>
                            <div class="field"><label>Hasil *</label><select class="select" name="result_path"
                                    required>@foreach(['REUSE', 'DONATION', 'REPAIR_ASSESSMENT', 'TECHNICAL_ASSESSMENT', 'PARTS_RECOVERY', 'SPECIAL_HANDLING', 'RECOVERY'] as $path)
                                        <option value="{{ $path }}" @selected($r->result_path === $path)>
                                    {{ \App\Support\SirkelUi::label($path) }}</option>@endforeach
                                </select></div>
                            <div class="field"><label>Prioritas *</label><input class="input" type="number" name="priority"
                                    min="1" value="{{ $r->priority }}" required></div>
                            <div class="field full"><label>Penjelasan warga</label><textarea class="textarea"
                                    name="explanation_template">{{ $r->explanation_template }}</textarea></div>
                            <label class="chip-check"><input type="checkbox" name="active" value="1" @checked($r->active)>
                                Aktif</label>
                            <div class="field full"><button class="btn">Simpan Aturan</button></div>
                        </form>
                    </details>
                @endforeach
            </div>

            <details class="admin-create-box mt-16">
                <summary><strong>+ Tambah aturan rekomendasi</strong></summary>
                <form class="form-grid mt-16" method="post" action="{{ route('admin.master.rule') }}" data-rule-builder>
                    @csrf
                    <div class="field full"><label>Nama aturan *</label><input class="input" name="name"
                            placeholder="Contoh: Perangkat mati dan tidak layak diperbaiki" required></div>
                    <div class="field"><label>Jika jawaban pada *</label><select class="select" name="condition_field_1"
                            data-rule-field="1" required>
                            <option value="">Pilih pertanyaan</option>@foreach($ruleFields as $f)
                            <option value="{{ $f['code'] }}">{{ $f['label'] }}</option>@endforeach
                        </select></div>
                    <div class="field"><label>Jawabannya *</label><select class="select" name="condition_value_1"
                            data-rule-value="1" required>
                            <option value="">Pilih pertanyaan dulu</option>
                        </select></div>
                    <div class="field"><label>Syarat kedua</label><select class="select" name="condition_field_2"
                            data-rule-field="2">
                            <option value="">Tidak ada</option>@foreach($ruleFields as $f)
                            <option value="{{ $f['code'] }}">{{ $f['label'] }}</option>@endforeach
                        </select></div>
                    <div class="field"><label>Jawabannya</label><select class="select" name="condition_value_2"
                            data-rule-value="2">
                            <option value="">-</option>
                        </select></div>
                    <div class="field"><label>Syarat ketiga</label><select class="select" name="condition_field_3"
                            data-rule-field="3">
                            <option value="">Tidak ada</option>@foreach($ruleFields as $f)
                            <option value="{{ $f['code'] }}">{{ $f['label'] }}</option>@endforeach
                        </select></div>
                    <div class="field"><label>Jawabannya</label><select class="select" name="condition_value_3"
                            data-rule-value="3">
                            <option value="">-</option>
                        </select></div>
                    <div class="field"><label>Maka rekomendasikan *</label><select class="select" name="result_path"
                            required>@foreach(['REUSE', 'DONATION', 'REPAIR_ASSESSMENT', 'TECHNICAL_ASSESSMENT', 'PARTS_RECOVERY', 'SPECIAL_HANDLING', 'RECOVERY'] as $path)
                            <option value="{{ $path }}">{{ \App\Support\SirkelUi::label($path) }}</option>@endforeach
                        </select></div>
                    <div class="field"><label>Prioritas *</label><input class="input" type="number" name="priority"
                            value="100" min="1" required><small>Angka lebih kecil diperiksa lebih dahulu.</small></div>
                    <div class="field full"><label>Penjelasan warga</label><textarea class="textarea"
                            name="explanation_template"></textarea></div>
                    <label class="chip-check"><input type="checkbox" name="active" value="1" checked> Aktif</label>
                    <div class="field full"><button class="btn btn-primary">Simpan Aturan</button></div>
                </form>
            </details>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        window.SIRKEL_RULE_FIELDS = @json($ruleFields->keyBy('code'));
        document.addEventListener('DOMContentLoaded', () => {
            bindMasterDataWorkspace(document.querySelector('[data-master-tabs]'));
            document.querySelectorAll('[data-rule-builder]').forEach(form => bindRuleBuilder(form, window.SIRKEL_RULE_FIELDS));
        });
    </script>
@endpush