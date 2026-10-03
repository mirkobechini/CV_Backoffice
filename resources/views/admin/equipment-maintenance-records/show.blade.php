@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Appuntamenti Attrezzature'), 'url' => route('admin.equipment-maintenance-records.index')],
        ['label' => $equipmentMaintenanceRecord->activity_type ?? __('Appuntamento')],
    ]" />
@endsection

@php
    $showCompleteButton = ! $equipmentMaintenanceRecord->return_date;
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.equipment-maintenance-records.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        @if ($showCompleteButton)
            <x-admin.complete-equipment-maintenance-modal :equipmentMaintenanceRecord="$equipmentMaintenanceRecord" />
        @endif
        <a href="{{ route('admin.equipment-maintenance-records.edit', ['equipmentMaintenanceRecord' => $equipmentMaintenanceRecord->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $equipmentMaintenanceRecord->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple));"><i
                class="fa-solid fa-calendar-check"></i></div>
        <div class="dl-title">
            <h1>{{ $equipmentMaintenanceRecord->activity_type ?? __('Appuntamento') }}</h1>
            <div class="sub">{{ $equipmentMaintenanceRecord->provider->name ?? 'N/A' }}</div>
        </div>
        <div class="dl-status">
            @if ($equipmentMaintenanceRecord->return_date)
                <span class="badge b-green">{{ __('Completato') }}</span>
            @else
                <span class="badge b-amber">{{ __('In programma') }}</span>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli appuntamento') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Fornitore') }}</span>
                <span class="v">
                    @if ($equipmentMaintenanceRecord->provider)
                        <a class="dl-veh-link" href="{{ route('admin.providers.show', $equipmentMaintenanceRecord->provider->id) }}">
                            {{ $equipmentMaintenanceRecord->provider->name }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data appuntamento') }}</span>
                <span class="v">{{ $equipmentMaintenanceRecord->appointment_date_formatted }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data restituzione') }}</span>
                <span class="v">{{ $equipmentMaintenanceRecord->return_date_formatted ?? '—' }}</span>
            </div>
            @if ($equipmentMaintenanceRecord->notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Note') }}</span>
                    <span class="v">{{ $equipmentMaintenanceRecord->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Attrezzature coinvolte') }}</h3>
        </div>
        <div class="body">
            @forelse ($equipmentMaintenanceRecord->equipments as $equipment)
                <div class="dl-kv">
                    <span class="k">{{ $equipment->equipmentType->name ?? 'N/A' }}</span>
                    <span class="v">
                        <a class="dl-veh-link" href="{{ route('admin.equipments.show', $equipment->id) }}">
                            {{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
                            · {{ $equipment->serial_number ?? 'N/A' }}
                            <span class="arrow">›</span>
                        </a>
                    </span>
                </div>
            @empty
                <p class="empty">{{ __('Nessuna attrezzatura collegata.') }}</p>
            @endforelse
        </div>
    </div>

    @if ($equipmentMaintenanceRecord->issues->isNotEmpty())
        <div class="dl-info-card">
            <div class="head">
                <h3>{{ __('Guasti collegati') }}</h3>
            </div>
            <div class="body">
                @foreach ($equipmentMaintenanceRecord->issues as $issue)
                    @php
                        $issueBadgeClass = match ($issue->status_color) {
                            'red' => 'b-red',
                            'yellow' => 'b-amber',
                            'green' => 'b-green',
                            default => 'b-gray',
                        };
                    @endphp
                    <div class="dl-kv">
                        <span class="k">{{ $issue->equipment->name ?? 'N/A' }}</span>
                        <span class="v">
                            <a class="dl-veh-link" href="{{ route('admin.equipment-issues.show', $issue->id) }}">
                                {{ $issue->description }}
                                <span class="arrow">›</span>
                            </a>
                            <span class="badge {{ $issueBadgeClass }}">{{ $issue->status_label }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-admin.delete-modal type="equipmentmaintenancerecord" :object="$equipmentMaintenanceRecord" />
@endsection
