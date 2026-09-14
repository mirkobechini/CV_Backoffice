@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Scadenze'), 'url' => route('admin.deadlines.index')],
        ['label' => $deadline->type],
    ]" />
@endsection

@php
    $icons = [
        'tagliando' => 'fa-solid fa-oil-can',
        'cinghia' => 'fa-solid fa-gear',
        'revisione' => 'fa-solid fa-clipboard-check',
        'ossigeno' => 'fa-solid fa-wind',
    ];
    $badgeClass = match ($deadline->status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
    $valClass = match ($deadline->status_color) {
        'red' => 'red',
        'yellow' => 'amber',
        'green' => 'green',
        default => '',
    };
    $daysDiff = $deadline->due_date ? \Carbon\Carbon::today()->diffInDays($deadline->due_date, false) : null;
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.deadlines.edit', ['deadline' => $deadline->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $deadline->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar"><i class="{{ $icons[$deadline->type_slug] ?? 'fa-solid fa-clock' }}"></i></div>
        <div class="dl-title">
            <h1>{{ $deadline->type }}</h1>
            <div class="sub">{{ $deadline->vehicle->internal_code ?? 'N/A' }}
                @if ($deadline->vehicle)
                    · {{ $deadline->vehicle->license_plate }} · {{ $deadline->vehicle->brand?->name ?? 'N/A' }}
                    {{ $deadline->vehicle->carModel?->name ?? '' }}
                @endif
            </div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $deadline->status_label }}</span>
            @if ($daysDiff !== null)
                <span class="hint">
                    @if ($daysDiff < 0)
                        {{ __('scaduta da :n giorni', ['n' => abs($daysDiff)]) }}
                    @else
                        {{ __('in scadenza tra :n giorni', ['n' => $daysDiff]) }}
                    @endif
                </span>
            @endif
        </div>
    </div>

    <div class="dl-countdown">
        @if ($deadline->date_remaining_label)
            <div class="dl-cd-card">
                <div class="lbl">{{ __('Giorni mancanti') }}</div>
                <div class="val {{ $valClass }}">{{ $deadline->date_remaining_label }}</div>
                <div class="sub">{{ __('scadenza il :d', ['d' => $deadline->due_date?->format('d/m/Y') ?? '—']) }}
                </div>
            </div>
        @endif
        @if ($deadline->km_remaining_label)
            <div class="dl-cd-card">
                <div class="lbl">{{ __('Km mancanti') }}</div>
                <div class="val {{ $valClass }}">{{ $deadline->km_remaining_label }}</div>
                <div class="sub">
                    {{ __('target :n km', ['n' => number_format($deadline->last_mileage + $deadline->interval_km, 0, ',', '.')]) }}
                </div>
            </div>
        @endif
        @if ($deadline->last_mileage !== null)
            <div class="dl-cd-card">
                <div class="lbl">{{ __('Km ultimo intervento') }}</div>
                <div class="val">{{ number_format($deadline->last_mileage, 0, ',', '.') }}</div>
            </div>
        @endif
        @if ($deadline->interval_days !== null || $deadline->interval_km !== null)
            <div class="dl-cd-card">
                <div class="lbl">{{ __('Intervallo') }}</div>
                <div class="val">
                    @if ($deadline->interval_days !== null)
                        {{ $deadline->interval_days }} {{ __('giorni') }}
                    @endif
                </div>
                @if ($deadline->interval_km !== null)
                    <div class="sub">{{ __('ogni :n km', ['n' => number_format($deadline->interval_km, 0, ',', '.')]) }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli scadenza') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($deadline->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $deadline->vehicle->id) }}">
                            {{ $deadline->vehicle->internal_code }} · {{ $deadline->vehicle->license_plate }} ·
                            {{ $deadline->vehicle->brand?->name ?? 'N/A' }} {{ $deadline->vehicle->carModel?->name ?? '' }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data di scadenza') }}</span>
                <span class="v">{{ $deadline->due_date_formatted ?? 'N/A' }}</span>
            </div>
            @if ($deadline->last_mileage !== null)
                <div class="dl-kv">
                    <span class="k">{{ __("Km all'ultimo intervento") }}</span>
                    <span class="v">{{ number_format($deadline->last_mileage, 0, ',', '.') }}</span>
                </div>
            @endif
            @if ($deadline->interval_km !== null)
                <div class="dl-kv">
                    <span class="k">{{ __('Intervallo km') }}</span>
                    <span class="v">{{ number_format($deadline->interval_km, 0, ',', '.') }}</span>
                </div>
            @endif
            @if ($deadline->last_mileage !== null && $deadline->interval_km !== null)
                <div class="dl-kv">
                    <span class="k">{{ __('Km di scadenza') }}</span>
                    <span
                        class="v">{{ number_format($deadline->last_mileage + $deadline->interval_km, 0, ',', '.') }}</span>
                </div>
            @endif
            @if ($deadline->interval_days !== null)
                <div class="dl-kv">
                    <span class="k">{{ __('Intervallo giorni') }}</span>
                    <span class="v">{{ $deadline->interval_days }}</span>
                </div>
            @endif
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $deadline->status_label }}</span></span>
            </div>
            @if ($deadline->renewsDeadline)
                <div class="dl-kv">
                    <span class="k">{{ __('Rinnova') }}</span>
                    <span class="v">
                        <a class="dl-veh-link"
                            href="{{ route('admin.deadlines.show', $deadline->renewsDeadline) }}">
                            {{ __('Scadenza del :d', ['d' => $deadline->renewsDeadline->due_date_formatted ?? 'N/A']) }}
                            <span class="arrow">›</span>
                        </a>
                    </span>
                </div>
            @endif
            @if ($deadline->renewedByDeadline)
                <div class="dl-kv">
                    <span class="k">{{ __('Rinnovata da') }}</span>
                    <span class="v">
                        <a class="dl-veh-link"
                            href="{{ route('admin.deadlines.show', $deadline->renewedByDeadline) }}">
                            {{ __('Scadenza del :d', ['d' => $deadline->renewedByDeadline->due_date_formatted ?? 'N/A']) }}
                            <span class="arrow">›</span>
                        </a>
                    </span>
                </div>
            @endif
        </div>
    </div>

    <x-admin.delete-modal type="deadline" :object="$deadline" />
@endsection
