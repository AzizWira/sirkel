@extends('layouts.app')

@section('title', 'Barang Saya · SIRKEL')

@section('topbar', 'Barang')

@section('content')
    <div class="page-head">
        <div>
            <h2>Barang Elektronik</h2>
            <p>Daftar ini menampilkan barang yang sudah mulai diproses. Draft yang belum diproses tetap berada di Keranjang.
            </p>
        </div>
        <div class="cluster"><a class="btn" href="{{ route('user.cart.index') }}">Keranjang</a><a class="btn"
                href="{{ route('user.bulk.create') }}"><x-icon name="sparkles" size="15" /> Bulk AI</a><a
                class="btn btn-primary" href="{{ route('user.assets.create') }}">+ Tambah Barang</a></div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode Paspor</th>
                    <th>Barang</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Jalur</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($assets as $a)
                    <tr>
                        <td><strong>{{ $a->passport_code }}</strong></td>
                        <td>{{ $a->custom_item_name ?: $a->category->name }}
                            <div class="text-sm muted">{{ $a->brand }} {{ $a->model_name }}</div>
                        </td>
                        <td>{{ \App\Support\SirkelUi::label($a->tracking_type) }}{{ $a->tracking_type === 'batch' ? ' · ' . $a->quantity . ' unit' : '' }}
                        </td>
                        <td><span class="badge">{{ \App\Support\SirkelUi::assetProgress($a->status, $a->final_path) }}</span>
                        </td>
                        <td>{{ $a->preliminary_path ? \App\Support\SirkelUi::label($a->preliminary_path) : '-' }}</td>
                        <td><a class="btn btn-sm" href="{{ route('user.assets.show', $a) }}">Detail</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $assets->links() }}</div>
@endsection