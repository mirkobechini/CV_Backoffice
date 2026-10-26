@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tessere carburante'), 'url' => route('admin.fuel-cards.index')],
        ['label' => __('Nuova tessera')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova tessera') }}</h1>
        <a href="{{ request('back', route('admin.fuel-cards.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="fuel-card-form" method="POST" action="{{ route('admin.fuel-cards.store') }}"
            data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli tessera') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="card_number">{{ __('Numero tessera') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('card_number') is-invalid @enderror" id="card_number"
                            name="card_number" value="{{ old('card_number') }}" maxlength="255" required>
                        @error('card_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="provider">{{ __('Fornitore') }}</label>
                        <input type="text" class="input @error('provider') is-invalid @enderror" id="provider"
                            name="provider" value="{{ old('provider') }}" maxlength="255"
                            placeholder="{{ __('es. Eni, Q8, IP') }}">
                        @error('provider')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                    <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}"
                                {{ old('status', 'assigned') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
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
                            <option value="{{ $vehicle->id }}" {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
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
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="fuel-card-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva tessera') }}
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
                // "retired" mostra il campo come "assigned" (serve a
                // mantenere il legame con chi la usava) ma senza svuotarlo
                // o renderlo obbligatorio: solo jolly/non assegnata lo
                // nascondono e lo azzerano.
                const showsVehicle = statusSelect.value === 'assigned' || statusSelect.value === 'retired';
                vehicleField.style.display = showsVehicle ? '' : 'none';
                if (!showsVehicle) vehicleSelect.value = '';
            }

            statusSelect.addEventListener('change', toggleVehicleField);
            toggleVehicleField();
        });
    </script>
@endsection
