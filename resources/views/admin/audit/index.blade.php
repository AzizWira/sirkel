@extends('layouts.app')

@section('title', 'Riwayat Sistem · SIRKEL')

@section('topbar', 'Riwayat Sistem')

@section('content')
    <div class="page-head">
        <div>
            <h2>Riwayat Perubahan Sistem</h2>
            <p>Catatan perubahan penting pada sistem.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pelaku</th>
                    <th>Tindakan</th>
                    <th>Data</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $l)
                    <tr>
                        <td class="nowrap">{{ $l->created_at->format('d M Y H:i:s') }}</td>
                        <td>{{ $l->user?->name ?? 'Sistem' }}</td>
                        <td>{{ \App\Support\SirkelUi::label($l->action) }}</td>
                        <td>{{ \App\Support\SirkelUi::resource($l->auditable_type) }} #{{ $l->auditable_id ?? '-' }}</td>
                        <td><span class="mono">{{ $l->ip_address }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty">Belum ada riwayat perubahan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>{{ $logs->links() }}
@endsection