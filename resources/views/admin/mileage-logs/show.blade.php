@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Chilometraggi'), 'url' => route('admin.mileage-logs.index')],
        ['label' => $mileageLog->vehicle->internal_code ?? 'N/A'],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.mileage-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.mileage-logs.edit', ['mileageLog' => $mileageLog->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $mileageLog->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-gauge-high"></i></div>
        <div class="dl-title">
            <h1>{{ number_format($mileageLog->mileage, 0, ',', '.') }} km</h1>
            <div class="sub">{{ $mileageLog->vehicle->internal_code ?? 'N/A' }} ·
                {{ $mileageLog->vehicle->license_plate ?? 'N/A' }}</div>
        </div>
        <div class="dl-status">
            <span class="hint">{{ $mileageLog->log_date_formatted ?? 'N/A' }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli registro') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($mileageLog->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $mileageLog->vehicle->id) }}">
                            {{ $mileageLog->vehicle->internal_code }} · {{ $mileageLog->vehicle->license_plate }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data del registro') }}</span>
                <span class="v">{{ $mileageLog->log_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Chilometraggio') }}</span>
                <span class="v">{{ number_format($mileageLog->mileage, 0, ',', '.') }} km</span>
            </div>
        </div>
    </div>

    <x-admin.delete-modal type="mileageLog" :object="$mileageLog" />
@endsection
