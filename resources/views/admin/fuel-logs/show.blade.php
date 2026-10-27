@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Carburante'), 'url' => route('admin.fuel-logs.index')],
        ['label' => $fuelLog->vehicle->internal_code ?? 'N/A'],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.fuel-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.fuel-logs.edit', ['fuelLog' => $fuelLog->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $fuelLog->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-gas-pump"></i></div>
        <div class="dl-title">
            <h1>{{ number_format($fuelLog->liters, 2, ',', '.') }} L</h1>
            <div class="sub">{{ $fuelLog->vehicle->internal_code ?? 'N/A' }} ·
                {{ $fuelLog->vehicle->license_plate ?? 'N/A' }}</div>
        </div>
        <div class="dl-status">
            <span class="hint">{{ $fuelLog->log_date_formatted ?? 'N/A' }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli rifornimento') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($fuelLog->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $fuelLog->vehicle->id) }}">
                            {{ $fuelLog->vehicle->internal_code }} · {{ $fuelLog->vehicle->license_plate }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Alimentazione') }}</span>
                <span class="v">
                    @if ($fuelLog->vehicle?->fuel_type)
                        <x-admin.status-badge
                            :color="\App\Models\Vehicle::fuelTypeBadgeColors()[$fuelLog->vehicle->fuel_type] ?? 'gray'">
                            {{ $fuelLog->vehicle->fuel_type_label }}
                        </x-admin.status-badge>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data rifornimento') }}</span>
                <span class="v">{{ $fuelLog->log_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Litri') }}</span>
                <span class="v">{{ number_format($fuelLog->liters, 2, ',', '.') }} L</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Costo totale') }}</span>
                <span class="v">
                    @if ($fuelLog->cost_total)
                        € {{ number_format($fuelLog->cost_total, 2, ',', '.') }}
                        @if ($fuelLog->cost_per_liter)
                            <span class="hint">({{ number_format($fuelLog->cost_per_liter, 3, ',', '.') }} €/L)</span>
                        @endif
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Chilometraggio') }}</span>
                <span class="v">{{ $fuelLog->mileage ? number_format($fuelLog->mileage, 0, ',', '.').' km' : 'N/A' }}</span>
            </div>
            @if ($fuelLog->fuelCard)
                <div class="dl-kv">
                    <span class="k">{{ __('Tessera usata') }}</span>
                    <span class="v">{{ $fuelLog->fuelCard->card_number }}</span>
                </div>
            @endif
        </div>
    </div>

    <x-admin.delete-modal type="fuelLog" :object="$fuelLog" />
@endsection
