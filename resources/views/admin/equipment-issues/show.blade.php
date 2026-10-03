@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Guasti Attrezzature'), 'url' => route('admin.equipment-issues.index')],
        ['label' => $equipmentIssue->description],
    ]" />
@endsection

@php
    $badgeClass = match ($equipmentIssue->status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.equipment-issues.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.equipment-issues.edit', ['equipmentIssue' => $equipmentIssue->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $equipmentIssue->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,#f87171,#dc2626);"><i
                class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="dl-title">
            <h1>{{ $equipmentIssue->description }}</h1>
            <div class="sub">{{ $equipmentIssue->equipment->name ?: ($equipmentIssue->equipment->equipmentType->name ?? 'N/A') }}</div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $equipmentIssue->status_label }}</span>
            <span class="hint">{{ __('dal :d', ['d' => $equipmentIssue->event_date_formatted ?? 'N/A']) }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli guasto') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Attrezzatura') }}</span>
                <span class="v">
                    <a class="dl-veh-link" href="{{ route('admin.equipments.show', $equipmentIssue->equipment->id) }}">
                        {{ $equipmentIssue->equipment->name ?: ($equipmentIssue->equipment->equipmentType->name ?? 'N/A') }}
                        · {{ $equipmentIssue->equipment->serial_number ?? 'N/A' }}
                        <span class="arrow">›</span>
                    </a>
                </span>
            </div>
            @if ($equipmentIssue->equipment->vehicle)
                <div class="dl-kv">
                    <span class="k">{{ __('Veicolo') }}</span>
                    <span class="v">
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $equipmentIssue->equipment->vehicle->id) }}">
                            {{ $equipmentIssue->equipment->vehicle->internal_code }}
                            <span class="arrow">›</span>
                        </a>
                    </span>
                </div>
            @endif
            <div class="dl-kv">
                <span class="k">{{ __('Data del guasto') }}</span>
                <span class="v">{{ $equipmentIssue->event_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $equipmentIssue->status_label }}</span></span>
            </div>
        </div>
    </div>

    <x-admin.delete-modal type="equipmentissue" :object="$equipmentIssue" />
@endsection
