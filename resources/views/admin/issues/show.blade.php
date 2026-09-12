@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Guasti'), 'url' => route('admin.issues.index')],
        ['label' => $issue->description],
    ]" />
@endsection

@php
    $badgeClass = match ($issue->status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.issues.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        @if ($issue->status !== 'closed')
            <a class="btn success"
                href="{{ route('admin.maintenance-records.create', ['issue_id' => $issue->id, 'vehicle_id' => $issue->vehicle_id, 'back' => url()->full()]) }}">
                <i class="fa-solid fa-calendar-check"></i> {{ __('Prenota appuntamento') }}
            </a>
        @endif
        <a href="{{ route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()]) }}" class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,#f87171,#dc2626);"><i
                class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="dl-title">
            <h1>{{ $issue->description }}</h1>
            <div class="sub">{{ $issue->vehicle->internal_code ?? 'N/A' }}
                @if ($issue->vehicle)
                    · {{ $issue->vehicle->brand?->name ?? 'N/A' }} {{ $issue->vehicle->carModel?->name ?? '' }}
                @endif
            </div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $issue->status_label }}</span>
            <span class="hint">{{ __('dal :d', ['d' => $issue->event_date_formatted ?? 'N/A']) }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli guasto') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($issue->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $issue->vehicle->id) }}">
                            {{ $issue->vehicle->internal_code }} · {{ $issue->vehicle->license_plate }} ·
                            {{ $issue->vehicle->brand?->name ?? 'N/A' }} {{ $issue->vehicle->carModel?->name ?? '' }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data del guasto') }}</span>
                <span class="v">{{ $issue->event_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $issue->status_label }}</span></span>
            </div>
        </div>
    </div>

    @if ($issue->notes)
        <div class="notes-card">
            <h4>{{ __('Note') }}</h4>
            <p>{{ $issue->notes }}</p>
        </div>
    @endif

    @if ($issue->photo)
        <div class="photo-card">
            <h4>{{ __('Immagine') }}</h4>
            <img src="{{ asset('storage/' . $issue->photo) }}" alt="{{ __('Immagine del guasto') }}">
        </div>
    @endif

    <x-admin.delete-modal type="issue" :object="$issue" />
@endsection
