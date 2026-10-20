@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tessere carburante'), 'url' => route('admin.fuel-cards.index')],
        ['label' => $fuelCard->card_number, 'url' => route('admin.fuel-cards.show', $fuelCard->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica tessera') }}</h1>
        <a href="{{ request('back', route('admin.fuel-cards.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="fuel-card-form" method="POST" action="{{ route('admin.fuel-cards.update', $fuelCard->id) }}"
            data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli tessera') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="card_number">{{ __('Numero tessera') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('card_number') is-invalid @enderror" id="card_number"
                            name="card_number" value="{{ old('card_number', $fuelCard->card_number) }}" maxlength="255"
                            required>
                        @error('card_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="provider">{{ __('Fornitore') }}</label>
                        <input type="text" class="input @error('provider') is-invalid @enderror" id="provider"
                            name="provider" value="{{ old('provider', $fuelCard->provider) }}" maxlength="255"
                            placeholder="{{ __('es. Eni, Q8, IP') }}">
                        @error('provider')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                    <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="assigned" {{ old('status', $fuelCard->status) == 'assigned' ? 'selected' : '' }}>
                            {{ __('Assegnata a un veicolo') }}</option>
                        <option value="jolly" {{ old('status', $fuelCard->status) == 'jolly' ? 'selected' : '' }}>
                            {{ __('Jolly (condivisa tra tutti i veicoli)') }}</option>
                        <option value="unassigned"
                            {{ old('status', $fuelCard->status) == 'unassigned' ? 'selected' : '' }}>
                            {{ __('Non ancora assegnata') }}</option>
                    </select>
                    @error('status')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field" id="vehicle-field">
                    <label for="vehicle_id">{{ __('Veicolo') }}</label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id">
                        <option value="">{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                {{ old('vehicle_id', $fuelCard->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }} · {{ $vehicle->license_plate }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $fuelCard->notes) }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="fuel-card-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.fuel-cards.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            const vehicleField = document.getElementById('vehicle-field');
            const vehicleSelect = document.getElementById('vehicle_id');

            function toggleVehicleField() {
                const isAssigned = statusSelect.value === 'assigned';
                vehicleField.style.display = isAssigned ? '' : 'none';
                if (!isAssigned) vehicleSelect.value = '';
            }

            statusSelect.addEventListener('change', toggleVehicleField);
            toggleVehicleField();
        });
    </script>
@endsection
