@extends('layouts.app')

@section('title', 'Aktivitas · SIRKEL')

@section('topbar', 'Aktivitas')

@section('content')
    <div class="page-head">
        <div>
            <h2>Aktivitas</h2>
            <p>Riwayat perjalanan elektronik yang Anda daftarkan.</p>
        </div>
    </div>
    <div class="card">
        <div class="timeline">
            @forelse($events as $e)
                <div class="timeline-item"><span class="timeline-dot"></span>
                    <div><strong>{{ $e->title }}</strong>
                        <div class="text-sm muted">{{ $e->asset->passport_code }} · {{ $e->occurred_at->format('d M Y H:i') }}
                        </div>
                        <p class="text-sm">{{ $e->description }}</p>
                    </div>
                </div>
            @empty
                <div class="empty">Belum ada aktivitas.</div>
            @endforelse
        </div>
    </div>
    <div style="margin-top:16px">{{ $events->links() }}</div>
@endsection