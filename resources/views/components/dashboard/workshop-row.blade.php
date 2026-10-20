@props(['inWorkshopRecords', 'upcomingAppointments'])

{{-- Veicoli in officina + Prossimi appuntamenti (affiancate: un appuntamento di oggi sta solo nella prima, mai in entrambe) --}}
<div class="dash-grid2">
    @if ($inWorkshopRecords->isNotEmpty())
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                            class="fa-solid fa-wrench"></i></span>{{ __('Veicoli in officina') }}</h3>
            </div>
            <div class="body">
                @foreach ($inWorkshopRecords as $record)
                    @php
                        $daysIn = max(1, (int) $record->appointment_date->diffInDays(\Illuminate\Support\Carbon::today()));
                    @endphp
                    <a href="{{ route('admin.maintenance-records.show', $record->id) }}" class="dash-list-item">
                        <div>
                            <div class="name">{{ $record->vehicle->internal_code }}</div>
                            <div class="meta">{{ $record->vehicle->license_plate }} ·
                                {{ $record->provider->name ?? __('Fornitore non indicato') }}</div>
                        </div>
                        <span class="badge b-amber">
                            {{ trans_choice('da :count giorno|da :count giorni', $daysIn, ['count' => $daysIn]) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
    <div class="dash-card" @if ($inWorkshopRecords->isEmpty()) style="grid-column:1 / -1;" @endif>
        <div class="head">
            <h3><span class="ic" style="background:var(--green-soft);color:var(--green)"><i
                        class="fa-solid fa-calendar-check"></i></span>{{ __('Prossimi appuntamenti') }}</h3>
        </div>
        <div class="body">
            @forelse ($upcomingAppointments as $appointment)
                <a href="{{ route('admin.maintenance-records.show', $appointment->id) }}" class="dash-list-item">
                    <div>
                        <div class="name">
                            {{ $appointment->items->where('itemable_type', 'App\Models\Issue')->map(fn($item) => $item->itemable?->description)->filter()->implode(', ') ?: $appointment->activity_type }}
                        </div>
                        <div class="meta">{{ $appointment->vehicle->internal_code }} ·
                            {{ $appointment->appointment_date?->format('d/m/Y') }} · {{ $appointment->provider?->name }}
                        </div>
                    </div>
                    <span class="badge b-green">{{ $appointment->appointment_date?->format('d/m') }}{{ $appointment->appointment_time ? ' '.$appointment->appointment_time : '' }}</span>
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
</div>
