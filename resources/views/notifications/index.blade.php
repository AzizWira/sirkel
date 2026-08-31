@extends('layouts.app')

@section('title', 'Notifikasi · SIRKEL')
@section('topbar', 'Notifikasi')

@section('content')
@php($unreadCount = auth()->user()->unreadNotifications()->count())
<div class="page-head">
    <div>
        <h2>Notifikasi</h2>
    </div>
    @if($unreadCount > 0)
        <form method="post" action="{{ route('notifications.read-all') }}">
            @csrf
            <button class="btn"><x-icon name="bell-read" size="16" /> Baca semua</button>
        </form>
    @endif
</div>

<div class="stack notification-list">
    @forelse($notifications as $notification)
    @php($unread = is_null($notification->read_at))
    <a class="notice notification-item {{ $unread ? 'is-unread' : 'is-read' }}"
        href="{{ route('notifications.read', $notification->id) }}">
        <div class="notice-icon" aria-hidden="true">
            <x-icon name="{{ $unread ? 'bell-unread' : 'bell-read' }}" size="20" />
        </div>
        <div class="notification-copy">
            <div class="split notification-title-row">
                <strong>{{ $notification->data['title'] ?? 'Notifikasi SIRKEL' }}</strong>
                <span class="text-sm muted">{{ $notification->created_at->diffForHumans() }}</span>
            </div>
            <div class="text-sm muted">{{ $notification->data['message'] ?? '' }}</div>
            <div class="notification-state">{{ $unread ? 'Belum dibaca' : 'Sudah dibaca' }}</div>
        </div>
    </a>
    @empty
    <div class="card empty">Belum ada notifikasi.</div>
    @endforelse
</div>

{{ $notifications->links() }}
@endsection