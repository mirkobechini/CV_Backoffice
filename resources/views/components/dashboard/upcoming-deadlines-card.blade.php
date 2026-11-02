@props(['upcomingDeadlines'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--amber-soft);color:var(--amber)"><i
                    class="fa-solid fa-clock"></i></span>{{ __('Scadenze imminenti') }}</h3>
    </div>
    <div class="body">
        @forelse ($upcomingDeadlines as $deadline)
            <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="dash-list-item">
                <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                <div>
                    <div class="name">{{ $deadline->type }}</div>
                    <div class="meta">{{ $deadline->vehicle->internal_code }} ·
                        {{ __('scade tra :days giorni', ['days' => floor(\Carbon\Carbon::today()->diffInDays($deadline->due_date, false))]) }}
                    </div>
                </div>
                <span class="badge b-amber">{{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</span>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessuna scadenza imminente') }}</div>
                    <div class="meta">{{ __('Tutti i veicoli sono in regola') }}</div>
                </div>
            </div>
        @endforelse
        @if ($upcomingDeadlines->isNotEmpty())
            <a href="{{ route('admin.deadlines.index') }}" class="dash-see-all">{{ __('Vedi tutte le scadenze') }}
                ›</a>
        @endif
    </div>
</div>
