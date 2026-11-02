@props(['expiredDeadlines'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                    class="fa-solid fa-calendar-xmark"></i></span>{{ __('Scadenze scadute e non rinnovate') }}
        </h3>
    </div>
    <div class="body">
        @forelse ($expiredDeadlines as $deadline)
            @php
                $daysOverdue = $deadline->due_date
                    ? abs(floor(\Carbon\Carbon::today()->diffInDays($deadline->due_date, false)))
                    : 0;
                // Stessa soglia di GenerateNotifications::ESCALATION_THRESHOLD_DAYS:
                // oltre i 7 giorni di ritardo la badge diventa più
                // evidente (vedi .b-red.critical in app.css).
                $isEscalated = $daysOverdue >= 7;
            @endphp
            <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="dash-list-item">
                <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                <div>
                    <div class="name">{{ $deadline->type }}</div>
                    <div class="meta">{{ $deadline->vehicle->internal_code }} ·
                        {{ __('scaduta da :days giorni', ['days' => $daysOverdue]) }}
                    </div>
                </div>
                <span class="badge b-red {{ $isEscalated ? 'critical' : '' }}">{{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</span>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessuna scadenza scaduta') }}</div>
                    <div class="meta">{{ __('Tutte le scadenze sono in regola') }}</div>
                </div>
            </div>
        @endforelse
        @if ($expiredDeadlines->isNotEmpty())
            <a href="{{ route('admin.deadlines.index') }}" class="dash-see-all">{{ __('Vedi tutte le scadenze') }}
                ›</a>
        @endif
    </div>
</div>
