@props(['upcomingAppointments'])

{{-- Data a sinistra in un riquadro mese/giorno, come nel mockup (restyle
     "Saru", ADR §19) — prima badge data a destra come le altre card. --}}
<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--green-soft);color:var(--green)"><i
                    class="fa-solid fa-calendar-check"></i></span>{{ __('Prossimi appuntamenti') }}</h3>
    </div>
    <div class="body">
        @forelse ($upcomingAppointments as $appointment)
            <a href="{{ route('admin.maintenance-records.show', $appointment->id) }}" class="dash-list-item">
                <div class="dash-date-box">
                    <div class="month">{{ $appointment->appointment_date?->translatedFormat('M') }}</div>
                    <div class="day">{{ $appointment->appointment_date?->format('d') }}</div>
                </div>
                <div>
                    <div class="name">
                        {{ $appointment->items->where('itemable_type', 'App\Models\Issue')->map(fn($item) => $item->itemable?->description)->filter()->implode(', ') ?: $appointment->activity_type }}
                    </div>
                    <div class="meta">{{ $appointment->vehicle->internal_code }} ·
                        {{ $appointment->provider?->name }}
                        @if ($appointment->appointment_time)
                            · {{ $appointment->appointment_time }}
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessun appuntamento imminente') }}</div>
                    <div class="meta">{{ __('Tutti i veicoli sono in regola') }}</div>
                </div>
            </div>
        @endforelse
        @if ($upcomingAppointments->isNotEmpty())
            <a href="{{ route('admin.maintenance-records.index') }}"
                class="dash-see-all">{{ __('Vedi tutti gli appuntamenti') }} ›</a>
        @endif
    </div>
</div>
