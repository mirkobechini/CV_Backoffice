@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tipi di veicoli'), 'url' => route('admin.vehicle-types.index')],
        ['label' => $vehicleType->name],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.vehicle-types.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.vehicle-types.edit', ['vehicleType' => $vehicleType->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $vehicleType->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-truck"></i></div>
        <div class="dl-title">
            <h1>{{ $vehicleType->name }}</h1>
            <div class="sub">
                {{ __('Prima revisione dopo :n', ['n' => $vehicleType->first_inspection_months_formatted ?? 'N/A']) }}
            </div>
        </div>
        <div class="dl-status">
            @if ($vehicleType->needs_oxygen_check)
                <span class="badge b-green"><i class="fa-solid fa-check"></i> {{ __('Ossigeno richiesta') }}</span>
            @else
                <span class="badge b-gray"><i class="fa-solid fa-xmark"></i> {{ __('Ossigeno non richiesta') }}</span>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli tipo di veicolo') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Prima revisione dopo') }}</span>
                <span class="v">{{ $vehicleType->first_inspection_months_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Revisioni successive ogni') }}</span>
                <span class="v">{{ $vehicleType->regular_inspection_months_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Revisione ossigeno') }}</span>
                <span class="v">{{ $vehicleType->needs_oxygen_check ? __('Sì') : __('No') }}</span>
            </div>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Equipaggiamento necessario') }}</h3>
        </div>
        <div class="body">
            @forelse ($vehicleType->equipmentTypes as $equipmentType)
                <div class="dl-kv">
                    <span class="k">{{ $equipmentType->name }}</span>
                    <span class="v">× {{ $equipmentType->pivot->required_quantity }}</span>
                </div>
            @empty
                <p class="empty">{{ __('Nessun equipaggiamento richiesto per questo tipo di veicolo.') }}</p>
            @endforelse
        </div>
    </div>

    <x-admin.delete-modal type="vehicleType" :object="$vehicleType" />
@endsection
