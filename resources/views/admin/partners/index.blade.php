@extends('layouts.app')

@section('title','Mitra · SIRKEL')
@section('topbar','Mitra')

@section('content')
<div class="page-head"><div><h2>Mitra</h2></div></div>
<div class="table-wrap"><table class="mobile-table mobile-table-7"><thead><tr><th>Mitra</th><th>Lokasi</th><th>Layanan</th><th>Verifikasi</th><th>Operasional</th><th>KTP</th><th></th></tr></thead><tbody>
@forelse($partners as $partner)
<tr>
    <td><strong>{{ $partner->business_name }}</strong><div class="text-sm muted">{{ $partner->responsible_name }} · {{ $partner->user->email }}</div></td>
    <td>{{ $partner->district }}<div class="text-sm muted">Radius {{ $partner->pickup_radius_km }} km</div></td>
    <td><div class="cluster">
        @foreach($partner->capabilities as $capability)
            <span class="badge {{ $capability->status==='approved'?'success':($capability->status==='rejected'?'danger':'warning') }}">{{ \App\Support\SirkelUi::label($capability->capability) }}</span>
        @endforeach
    </div></td>
    <td><span class="badge {{ $partner->verification_status==='approved'?'success':($partner->verification_status==='rejected'?'danger':'warning') }}">{{ \App\Support\SirkelUi::label($partner->verification_status) }}</span></td>
    <td>
        @if($partner->verification_status==='approved')
            <span class="badge {{ ($partner->admin_status??'inactive')==='active'?'success':'danger' }}">{{ \App\Support\SirkelUi::label($partner->admin_status??'inactive') }}</span>
        @else
            <span class="muted">-</span>
        @endif
    </td>
    <td>{{ $partner->identity_file_path?'Tersedia':'Terhapus/tidak ada' }}</td>
    <td><a class="btn btn-sm" href="{{ route('admin.partners.show',$partner) }}">{{ $partner->verification_status==='approved'?'Kelola':'Tinjau' }}</a></td>
</tr>
@empty
<tr><td colspan="7" class="empty">Belum ada pengajuan.</td></tr>
@endforelse
</tbody></table></div>{{ $partners->links() }}
@endsection
