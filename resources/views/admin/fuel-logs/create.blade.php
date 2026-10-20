@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Carburante'), 'url' => route('admin.fuel-logs.index')],
        ['label' => __('Nuovo rifornimento')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo rifornimento') }}</h1>
        <a href="{{ request('back', route('admin.fuel-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="fuel-log-form" method="POST" action="{{ route('admin.fuel-logs.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli rifornimento') }}</h2>
                <div class="field">
                    <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id"
                        required>
                        <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }} · {{ $vehicle->brand?->name ?? 'N/A' }}
                                {{ $vehicle->carModel?->name ?? 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <x-form.date-input name="log_date" label="{{ __('Data rifornimento') }}" required />
                    <div class="field">
                        <label for="liters">{{ __('Litri') }} <span class="req">*</span></label>
                        <input type="number" step="0.01" class="input @error('liters') is-invalid @enderror"
                            id="liters" name="liters" value="{{ old('liters') }}" min="0.01"
                            placeholder="{{ __('es. 45.50') }}" required>
                        @error('liters')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="cost_total">{{ __('Costo totale (€)') }}</label>
                        <input type="number" step="0.01" class="input @error('cost_total') is-invalid @enderror"
                            id="cost_total" name="cost_total" value="{{ old('cost_total') }}" min="0"
                            placeholder="{{ __('es. 68.90') }}">
                        @error('cost_total')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="mileage">{{ __('Chilometraggio al rifornimento') }}</label>
                        <input type="number" class="input @error('mileage') is-invalid @enderror" id="mileage"
                            name="mileage" value="{{ old('mileage') }}" min="0" placeholder="{{ __('es. 87400') }}">
                        @error('mileage')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                @if ($fuelCards->isNotEmpty())
                    <div class="field">
                        <label for="fuel_card_id">{{ __('Tessera usata (opzionale)') }}</label>
                        <select class="select @error('fuel_card_id') is-invalid @enderror" id="fuel_card_id"
                            name="fuel_card_id">
                            <option value="">{{ __('Nessuna') }}</option>
                            @foreach ($fuelCards as $fuelCard)
                                <option value="{{ $fuelCard->id }}"
                                    {{ old('fuel_card_id') == $fuelCard->id ? 'selected' : '' }}>
                                    {{ $fuelCard->card_number }} ({{ $fuelCard->status_label }})
                                </option>
                            @endforeach
                        </select>
                        @error('fuel_card_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="form-actions">
                <button id="fuel-log-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva rifornimento') }}
                </button>
                <a href="{{ request('back', route('admin.fuel-logs.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
