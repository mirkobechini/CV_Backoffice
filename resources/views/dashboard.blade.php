@extends('layouts.app')

@section('content')

    {{-- Header: titolo + data odierna --}}
    <div class="page-header">
        <h1><i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}</h1>
        <div class="date">{{ __('Oggi,') }} {{ now()->translatedFormat('l j F Y') }}</div>
    </div>

    {{-- 6 KPI --}}
    <div class="dash-kpis">
        <a href="{{ route('admin.vehicles.index') }}" class="dash-kpi">
            <div class="ic k1"><i class="fa-solid fa-truck"></i></div>
            <div>
                <div class="lbl">{{ __('Veicoli totali') }}</div>
                <div class="val">{{ $totalVehicles }}</div>
            </div>
        </a>
        <a href="{{ route('admin.issues.index') }}" class="dash-kpi">
            <div class="ic k2"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="lbl">{{ __('Guasti aperti') }}</div>
                <div class="val">{{ $openIssues->count() }}</div>
            </div>
        </a>
        <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
            <div class="ic k3"><i class="fa-solid fa-clock"></i></div>
            <div>
                <div class="lbl">{{ __('Scadenze imminenti') }}</div>
                <div class="val">{{ $upcomingDeadlines->count() }}</div>
            </div>
        </a>
        <a href="{{ route('admin.deadlines.index') }}" class="dash-kpi">
            <div class="ic k4"><i class="fa-solid fa-calendar-xmark"></i></div>
            <div>
                <div class="lbl">{{ __('Scadenze scadute') }}</div>
                <div class="val">{{ $expiredDeadlines->count() }}</div>
            </div>
        </a>
        <a href="{{ route('admin.maintenance-records.index') }}" class="dash-kpi">
            <div class="ic k5"><i class="fa-solid fa-wrench"></i></div>
            <div>
                <div class="lbl">{{ __('In officina') }}</div>
                <div class="val">{{ $inWorkshopCount }}</div>
            </div>
        </a>
        <a href="{{ route('admin.equipments.index') }}" class="dash-kpi">
            <div class="ic k6"><i class="fa-solid fa-toolbox"></i></div>
            <div>
                <div class="lbl">{{ __('Attrez. in scadenza') }}</div>
                <div class="val">{{ $expiringEquipment->count() }}</div>
            </div>
        </a>
    </div>

    {{-- Riga 1: le due card scadenze (imminenti + scadute) affiancate --}}
    <div class="dash-grid2">
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--amber-soft);color:var(--amber)"><i
                            class="fa-solid fa-clock"></i></span>{{ __('Scadenze imminenti') }}</h3>
            </div>
            <div class="body">
                @forelse ($upcomingDeadlines as $deadline)
                    <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="dash-list-item">
                        <span class="dot leg-{{ $deadline->type_slug }}"></span>
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
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                            class="fa-solid fa-calendar-xmark"></i></span>{{ __('Scadenze scadute e non rinnovate') }}
                </h3>
            </div>
            <div class="body">
                @forelse ($expiredDeadlines as $deadline)
                    <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="dash-list-item">
                        <span class="dot leg-{{ $deadline->type_slug }}"></span>
                        <div>
                            <div class="name">{{ $deadline->type }}</div>
                            <div class="meta">{{ $deadline->vehicle->internal_code }} ·
                                {{ __('scaduta da :days giorni', ['days' => abs(floor(\Carbon\Carbon::today()->diffInDays($deadline->due_date, false)))]) }}
                            </div>
                        </div>
                        <span class="badge b-red">{{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</span>
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
    </div>

    {{-- Riga 2: Guasti aperti + Prossimi appuntamenti --}}
    <div class="dash-grid2">
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                            class="fa-solid fa-triangle-exclamation"></i></span>{{ __('Guasti aperti') }}</h3>
            </div>
            <div class="body">
                @forelse ($openIssues as $issue)
                    <a href="{{ route('admin.issues.show', $issue->id) }}" class="dash-list-item">
                        <div>
                            <div class="name">{{ $issue->description }}</div>
                            <div class="meta">{{ $issue->vehicle->internal_code }} ·
                                {{ $issue->event_date?->format('d/m/Y') }}</div>
                        </div>
                        <span
                            class="badge {{ match ($issue->status_color) {
                                'red' => 'b-red',
                                'yellow' => 'b-amber',
                                'green' => 'b-green',
                                default => 'b-gray',
                            } }}">{{ $issue->status_label }}</span>
                    </a>
                @empty
                    <div class="dash-empty">
                        <div>
                            <div class="name">{{ __('Nessun guasto aperto') }}</div>
                            <div class="meta">{{ __('Tutti i veicoli sono funzionanti') }}</div>
                        </div>
                    </div>
                @endforelse
                @if ($openIssues->isNotEmpty())
                    <a href="{{ route('admin.issues.index') }}" class="dash-see-all">{{ __('Vedi tutti i guasti') }}
                        ›</a>
                @endif
            </div>
        </div>
        <div class="dash-card">
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
                        <span class="badge b-green">{{ $appointment->appointment_date?->format('d/m') }}</span>
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

    {{-- Riga 3: Equipaggiamento (extra, non nel prototipo) + Attrezzature in scadenza --}}
    <div class="dash-grid2">
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--purple-soft);color:var(--purple)"><i
                            class="fa-solid fa-boxes-stacked"></i></span>{{ __('Equipaggiamento') }}</h3>
            </div>
            <div class="body">
                <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:6px;">
                    <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                        <span class="name" style="font-size:17px;">{{ $totalVehicles - $incompleteVehicles->count() }}</span>
                        <span class="meta">/ {{ $totalVehicles }} {{ __('mezzi completi') }}</span>
                        @if ($incompleteVehicles->isNotEmpty())
                            <span class="badge b-amber" style="margin-left:auto;">
                                {{ trans_choice('{1} :count mezzo da integrare|[2,*] :count mezzi da integrare', $incompleteVehicles->count(), ['count' => $incompleteVehicles->count()]) }}
                            </span>
                        @endif
                    </div>
                    <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                        <div style="height:100%;background:var(--green);width:{{ $totalVehicles > 0 ? (($totalVehicles - $incompleteVehicles->count()) / $totalVehicles) * 100 : 0 }}%;">
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.vehicles.index', ['filter' => 'incomplete']) }}"
                    class="dash-see-all">{{ __('Vedi veicoli da integrare') }} ›</a>
            </div>
        </div>
        <div class="dash-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--blue-soft);color:var(--blue)"><i
                            class="fa-solid fa-toolbox"></i></span>{{ __('Attrezzature in scadenza') }}</h3>
            </div>
            <div class="body">
                @forelse ($expiringEquipment as $equipment)
                    <a href="{{ route('admin.equipments.index') }}" class="dash-list-item">
                        <div>
                            <div class="name">{{ $equipment->name }}</div>
                            <div class="meta">{{ $equipment->vehicle?->internal_code ?? '—' }} ·
                                @if ($equipment->expiration_date?->isPast())
                                    {{ __('scaduta') }}
                                @else
                                    {{ __('scade tra :days giorni', ['days' => \Carbon\Carbon::today()->diffInDays($equipment->expiration_date)]) }}
                                @endif
                            </div>
                        </div>
                        <span
                            class="badge {{ $equipment->expiration_date?->isPast() ? 'b-red' : 'b-amber' }}">{{ $equipment->expiration_date?->isPast() ? __('Scaduta') : \Carbon\Carbon::today()->diffInDays($equipment->expiration_date) . ' ' . __('gg') }}</span>
                    </a>
                @empty
                    <div class="dash-empty">
                        <div>
                            <div class="name">{{ __('Nessuna attrezzatura in scadenza') }}</div>
                            <div class="meta">{{ __('Tutte le attrezzature sono in regola') }}</div>
                        </div>
                    </div>
                @endforelse
                @if ($expiringEquipment->isNotEmpty())
                    <a href="{{ route('admin.equipments.index') }}"
                        class="dash-see-all">{{ __('Vedi tutte le attrezzature') }} ›</a>
                @endif
            </div>
        </div>
    </div>
@endsection
