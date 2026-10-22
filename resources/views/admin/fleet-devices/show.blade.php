@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Dispositivi pedaggio'), 'url' => route('admin.fleet-devices.index')],
        ['label' => $fleetDevice->code],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.fleet-devices.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.fleet-devices.edit', ['fleetDevice' => $fleetDevice->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $fleetDevice->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-road-barrier"></i></div>
        <div class="dl-title">
            <h1>{{ $fleetDevice->type_label }}</h1>
            <div class="sub">{{ $fleetDevice->code }}</div>
        </div>
        <div class="dl-status">
            @if ($fleetDevice->vehicle)
                <span class="hint">{{ $fleetDevice->vehicle->internal_code }} ·
                    {{ $fleetDevice->vehicle->license_plate }}</span>
            @else
                <span class="badge b-gray">{{ __('Non assegnato') }}</span>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli dispositivo') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Tipo') }}</span>
                <span class="v">{{ $fleetDevice->type_label }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Codice') }}</span>
                <span class="v">{{ $fleetDevice->code }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo attuale') }}</span>
                <span class="v">
                    @if ($fleetDevice->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $fleetDevice->vehicle->id) }}">
                            {{ $fleetDevice->vehicle->internal_code }} · {{ $fleetDevice->vehicle->license_plate }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            @if ($fleetDevice->provider_notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Intestatario/contratto') }}</span>
                    <span class="v">{{ $fleetDevice->provider_notes }}</span>
                </div>
            @endif
            @if ($fleetDevice->notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Note') }}</span>
                    <span class="v">{{ $fleetDevice->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="form-card" style="margin-top:16px;">
        <h2>{{ __('Riassegna a un altro veicolo') }}</h2>
        <form method="POST" action="{{ route('admin.fleet-devices.reassign', $fleetDevice->id) }}"
            data-single-submit="true">
            @csrf
            <div class="row2">
                <div class="field">
                    <label for="vehicle_id">{{ __('Nuovo veicolo') }} <span class="req">*</span></label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id"
                        required>
                        <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            @continue($vehicle->id === $fleetDevice->vehicle_id)
                            <option value="{{ $vehicle->id }}">{{ $vehicle->internal_code }} ·
                                {{ $vehicle->license_plate }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <x-form.date-input name="assigned_date" label="{{ __('Data riassegnazione') }}" required />
            </div>
            <div class="field">
                <label for="notes">{{ __('Note (opzionale)') }}</label>
                <input type="text" class="input" id="notes" name="notes" maxlength="255">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-right-left"></i> {{ __('Riassegna') }}
                </button>
            </div>
        </form>
        @if ($fleetDevice->vehicle_id)
            <form method="POST" action="{{ route('admin.fleet-devices.unassign', $fleetDevice->id) }}"
                style="margin-top:8px;"
                onsubmit="return confirm('{{ __('Sganciare il dispositivo dal veicolo attuale, senza assegnarlo a uno nuovo?') }}');">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn secondary">
                    <i class="fa-solid fa-link-slash"></i> {{ __('Sgancia dal veicolo') }}
                </button>
            </form>
        @endif
    </div>

    @if ($fleetDevice->assignments->isNotEmpty())
        <div class="table-card" style="margin-top:16px;">
            <div class="toolbar">
                <h2>{{ __('Storico assegnazioni') }}</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Data') }}</th>
                            <th>{{ __('Veicolo precedente') }}</th>
                            <th>{{ __('Veicolo assegnato') }}</th>
                            <th>{{ __('Targa') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fleetDevice->assignments as $assignment)
                            <tr>
                                <td>{{ $assignment->assigned_date_formatted ?? 'N/A' }}</td>
                                <td>{{ $assignment->previousVehicle?->internal_code ?? '—' }}</td>
                                <td>{{ $assignment->vehicle?->internal_code ?? 'N/A' }}</td>
                                <td>{{ $assignment->plate_snapshot ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <x-admin.delete-modal type="fleetDevice" :object="$fleetDevice" />
@endsection
