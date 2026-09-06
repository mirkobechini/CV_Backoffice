@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-bell me-2"></i>Notifiche</h1>
            <div class="ms-auto">
                <form action="{{ route('notifications.read-all') }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Segna tutte come lette</button>
                </form>
            </div>
        </div>

        @if ($notifications->isEmpty())
            <div class="alert alert-info">
                <i class="bi bi-check-circle me-2"></i>Nessuna notifica.
            </div>
        @else
            <div class="list-group">
                @foreach ($notifications as $notification)
                    <div
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $notification->is_read ? '' : 'list-group-item-primary' }}">
                        <div class="d-flex align-items-center gap-2">
                            <span class="me-2">{{ $notification->type_icon }}</span>
                            <div>
                                <div class="fw-bold {{ $notification->is_read ? '' : 'text-primary' }}">
                                    {{ $notification->title }}
                                    @if (!$notification->is_read)
                                        <span class="badge bg-primary ms-1">Nuova</span>
                                    @endif
                                </div>
                                @if ($notification->message)
                                    <div class="text-muted small">{{ $notification->message }}</div>
                                @endif
                                <div class="text-muted small">{{ $notification->created_at?->format('d/m/Y H:i') }}</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            @if ($notification->url)
                                <a href="{{ $notification->url }}" class="btn btn-sm btn-outline-primary">Apri</a>
                            @endif
                            @if (!$notification->is_read)
                                <form action="{{ route('notifications.read', $notification) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                        title="Segna come letta">
                                        <i class="bi bi-check2"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{ $notifications->links() }}
        @endif
    </div>
@endsection
