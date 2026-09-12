@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Notifiche')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Notifiche') }}</h1>
        @if ($notifications->where('is_read', false)->isNotEmpty())
            <form action="{{ route('notifications.read-all') }}" method="POST">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn">
                    <i class="fa-solid fa-check-double"></i> {{ __('Segna tutte come lette') }}
                </button>
            </form>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <div class="empty">{{ __('Nessuna notifica.') }}</div>
    @else
        <div class="notif-list">
            @foreach ($notifications as $notification)
                <div class="notif-item {{ $notification->is_read ? '' : 'unread' }}">
                    <span class="notif-icon">{{ $notification->type_icon }}</span>
                    <div class="notif-body">
                        <div class="notif-title">
                            {{ $notification->title }}
                            @if (! $notification->is_read)
                                <span class="badge b-blue">{{ __('Nuova') }}</span>
                            @endif
                        </div>
                        @if ($notification->message)
                            <div class="notif-msg">{{ $notification->message }}</div>
                        @endif
                        <div class="notif-time">{{ $notification->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <div class="notif-actions">
                        @if ($notification->url)
                            <a href="{{ $notification->url }}" class="btn sm">{{ __('Apri') }}</a>
                        @endif
                        @if (! $notification->is_read)
                            <form action="{{ route('notifications.read', $notification) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="mini-btn" title="{{ __('Segna come letta') }}">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($notifications->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $notifications->currentPage() }} / {{ $notifications->lastPage() }} ·
                    {{ $notifications->total() }}</span>
                <nav>
                    {{ $notifications->links() }}
                </nav>
            </div>
        @endif
    @endif
@endsection
