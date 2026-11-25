@props(['upcomingEquipmentAppointments'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--green-soft);color:var(--green)"><i
                    class="fa-solid fa-calendar-check"></i></span>{{ __('Prossimi appuntamenti attrezzature') }}</h3>
    </div>
    <div class="body">
        @forelse ($upcomingEquipmentAppointments as $record)
            <a href="{{ route('admin.equipment-maintenance-records.show', $record->id) }}" class="dash-list-item">
                <div class="dash-date-box">
                    <div class="month">{{ $record->appointment_date?->translatedFormat('M') }}</div>
                    <div class="day">{{ $record->appointment_date?->format('d') }}</div>
                </div>
                <div>
                    <div class="name">{{ $record->equipments->pluck('name')->implode(', ') ?: $record->activity_type }}</div>
                    <div class="meta">{{ $record->activity_type }}
                        @if ($record->provider)
                            · {{ $record->provider->name }}
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessun appuntamento imminente') }}</div>
                    <div class="meta">{{ __('Tutte le attrezzature sono in regola') }}</div>
                </div>
            </div>
        @endforelse
        @if ($upcomingEquipmentAppointments->isNotEmpty())
            <a href="{{ route('admin.equipment-maintenance-records.index') }}"
                class="dash-see-all">{{ __('Vedi tutti gli appuntamenti attrezzature') }} ›</a>
        @endif
    </div>
</div>
