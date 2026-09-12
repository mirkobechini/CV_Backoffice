@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A')],
    ]" />
@endsection

@php
    $badgeClass = match ($equipment->status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple));"><i
                class="fa-solid fa-fire-extinguisher"></i></div>
        <div class="dl-title">
            <h1>{{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}</h1>
            <div class="sub">{{ $equipment->equipmentType->name ?? 'N/A' }} ·
                {{ $equipment->serial_number ?: __('N/A') }}</div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $equipment->status_label }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli attrezzatura') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Numero di serie') }}</span>
                <span class="v">{{ $equipment->serial_number ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Tipo di attrezzatura') }}</span>
                <span class="v">{{ $equipment->equipmentType->name ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data revisione') }}</span>
                <span class="v">{{ $equipment->revision_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data scadenza') }}</span>
                <span class="v">{{ $equipment->expiration_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo associato') }}</span>
                <span class="v">
                    @if ($equipment->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $equipment->vehicle->id) }}">
                            {{ $equipment->vehicle->internal_code }} · {{ $equipment->vehicle->license_plate }} ·
                            {{ optional($equipment->vehicle->brand)->name }}
                            {{ optional($equipment->vehicle->carModel)->name }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $equipment->status_label }}</span></span>
            </div>
        </div>
    </div>

    <x-admin.delete-modal type="equipment" :object="$equipment" />
@endsection
