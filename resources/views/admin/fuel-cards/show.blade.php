@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tessere carburante'), 'url' => route('admin.fuel-cards.index')],
        ['label' => $fuelCard->card_number],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.fuel-cards.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.fuel-cards.edit', ['fuelCard' => $fuelCard->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $fuelCard->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-credit-card"></i></div>
        <div class="dl-title">
            <h1>{{ $fuelCard->card_number }}</h1>
            <div class="sub">{{ $fuelCard->provider ?? __('Fornitore non indicato') }}</div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $fuelCard->status === 'assigned' ? 'b-green' : ($fuelCard->status === 'jolly' ? 'b-blue' : 'b-gray') }}">
                {{ $fuelCard->status_label }}
            </span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli tessera') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Numero tessera') }}</span>
                <span class="v">{{ $fuelCard->card_number }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Fornitore') }}</span>
                <span class="v">{{ $fuelCard->provider ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v">{{ $fuelCard->status_label }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($fuelCard->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $fuelCard->vehicle->id) }}">
                            {{ $fuelCard->vehicle->internal_code }} · {{ $fuelCard->vehicle->license_plate }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            @if ($fuelCard->notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Note') }}</span>
                    <span class="v">{{ $fuelCard->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <x-admin.delete-modal type="fuelCard" :object="$fuelCard" />
@endsection
