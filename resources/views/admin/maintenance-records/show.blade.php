@extends('layouts.app')

@php
    $linkedIssues = $maintenanceRecord->items
        ->where('itemable_type', \App\Models\Issue::class)
        ->map(fn($item) => $item->itemable)
        ->filter();
    $linkedDeadlines = $maintenanceRecord->items
        ->where('itemable_type', \App\Models\Deadline::class)
        ->map(fn($item) => $item->itemable)
        ->filter();
    $issueDescriptions = $linkedIssues->pluck('description')->implode(', ');
    $title = $issueDescriptions !== '' ? $issueDescriptions : $maintenanceRecord->activity_type ?? __('Intervento');
    $badgeClass = fn($color) => match ($color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        'blue' => 'b-blue',
        'purple' => 'b-purple',
        default => 'b-gray',
    };
    $isCompleted = $maintenanceRecord->return_date !== null;
    $showCompleteButton =
        $maintenanceRecord->return_date === null &&
        $maintenanceRecord->items->where('itemable_type', \App\Models\Issue::class)->first()?->itemable?->status !== 'closed';
    $appointmentNotYetDue =
        $maintenanceRecord->appointment_date && \Illuminate\Support\Carbon::today()->lt($maintenanceRecord->appointment_date);
    $completeDisabledReason = $appointmentNotYetDue
        ? __("Non puoi completare l'appuntamento prima del :d.", ['d' => $maintenanceRecord->appointment_date_formatted])
        : null;
@endphp

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti'), 'url' => route('admin.maintenance-records.index')],
        ['label' => $title],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        @if ($showCompleteButton)
            <x-admin.complete-maintenance-modal :maintenanceRecord="$maintenanceRecord"
                :disabledReason="$completeDisabledReason" />
        @endif
        <a href="{{ route('admin.maintenance-records.edit', ['maintenanceRecord' => $maintenanceRecord->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $maintenanceRecord->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,#818cf8,#4f46e5);"><i
                class="fa-solid fa-calendar-check"></i></div>
        <div class="dl-title">
            <h1>{{ $title }}</h1>
            <div class="sub">{{ $maintenanceRecord->vehicle->internal_code ?? 'N/A' }}
                @if ($maintenanceRecord->vehicle)
                    · {{ $maintenanceRecord->vehicle->brand?->name ?? 'N/A' }}
                    {{ $maintenanceRecord->vehicle->carModel?->name ?? '' }}
                @endif
            </div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $isCompleted ? 'b-green' : 'b-blue' }}">
                {{ $isCompleted ? __('Completato') : __('In programma') }}
            </span>
            <span class="hint">{{ __('il :d', ['d' => $maintenanceRecord->appointment_date_formatted ?? 'N/A']) }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli appuntamento') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($maintenanceRecord->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $maintenanceRecord->vehicle->id) }}">
                            {{ $maintenanceRecord->vehicle->internal_code }} ·
                            {{ $maintenanceRecord->vehicle->license_plate }} ·
                            {{ $maintenanceRecord->vehicle->brand?->name ?? 'N/A' }}
                            {{ $maintenanceRecord->vehicle->carModel?->name ?? '' }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Officina') }}</span>
                <span class="v">{{ $maintenanceRecord->provider?->name ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data appuntamento') }}</span>
                <span class="v">{{ $maintenanceRecord->appointment_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data restituzione') }}</span>
                <span class="v">{{ $maintenanceRecord->return_date_formatted ?? 'N/A' }}</span>
            </div>
            @if ($maintenanceRecord->activity_type !== null)
                <div class="dl-kv">
                    <span class="k">{{ __('Tipo attività') }}</span>
                    <span class="v">{{ $maintenanceRecord->activity_type }}</span>
                </div>
            @endif
            @if ($maintenanceRecord->mileage_at_service !== null)
                <div class="dl-kv">
                    <span class="k">{{ __("Km all'appuntamento") }}</span>
                    <span class="v">{{ number_format($maintenanceRecord->mileage_at_service, 0, ',', '.') }} km</span>
                </div>
            @endif
        </div>
    </div>

    @if ($maintenanceRecord->notes)
        <div class="notes-card">
            <h4>{{ __('Note') }}</h4>
            <p>{{ $maintenanceRecord->notes }}</p>
        </div>
    @endif

    @if ($linkedIssues->isNotEmpty())
        <div class="dl-info-card">
            <div class="head">
                <h3>{{ __('Guasti collegati') }}</h3>
            </div>
            <div class="body">
                @foreach ($linkedIssues as $issue)
                    <div class="dl-kv">
                        <span class="k">{{ $issue->description }}
                            @if ($issue->event_date)
                                <span class="hint">— {{ $issue->event_date->format('d/m/Y') }}</span>
                            @endif
                        </span>
                        <span class="v"><span class="badge {{ $badgeClass($issue->status_color) }}">{{ $issue->status_label }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($linkedDeadlines->isNotEmpty())
        <div class="dl-info-card">
            <div class="head">
                <h3>{{ __('Scadenze collegate') }}</h3>
            </div>
            <div class="body">
                @foreach ($linkedDeadlines as $deadline)
                    <div class="dl-kv">
                        <span class="k">{{ $deadline->type }}
                            @if ($deadline->due_date)
                                <span class="hint">— {{ $deadline->due_date->format('d/m/Y') }}</span>
                            @endif
                        </span>
                        <span class="v"><span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->status_label }}</span></span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-admin.delete-modal type="maintenanceRecord" :object="$maintenanceRecord" />
@endsection
