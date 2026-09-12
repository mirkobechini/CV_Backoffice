@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Veicoli'), 'url' => route('admin.vehicles.index')],
        ['label' => __('Nuovo veicolo')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo veicolo') }}</h1>
        <a href="{{ request('back', route('admin.vehicles.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="vehicle-form" method="POST" action="{{ route('admin.vehicles.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Dettagli veicolo --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli veicolo') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="license_plate">{{ __('Targa') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('license_plate') is-invalid @enderror"
                            id="license_plate" name="license_plate" value="{{ old('license_plate') }}"
                            placeholder="{{ __('es. AB123CD') }}" style="text-transform:uppercase;" required>
                        @error('license_plate')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="internal_code">{{ __('Sigla') }}</label>
                        <input type="text" inputmode="numeric" class="input @error('internal_code') is-invalid @enderror"
                            id="internal_code" name="internal_code" value="{{ old('internal_code') }}"
                            placeholder="{{ __('es. 001') }}">
                        @error('internal_code')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                {{-- Sezione dinamica con Livewire per marca e modello --}}
                @livewire('vehicle-select', [
                    'brand_id' => old('brand_id'),
                    'car_model_id' => old('car_model_id'),
                ])

                <div class="row2">
                    <div class="field">
                        <label for="fuel_type">{{ __('Carburante') }}</label>
                        <select class="select @error('fuel_type') is-invalid @enderror" id="fuel_type" name="fuel_type">
                            <option value="" disabled selected>{{ __('Seleziona un carburante') }}</option>
                            <option value="benzina" {{ old('fuel_type') == 'benzina' ? 'selected' : '' }}>
                                {{ __('Benzina') }}</option>
                            <option value="diesel" {{ old('fuel_type') == 'diesel' ? 'selected' : '' }}>
                                {{ __('Diesel') }}</option>
                            <option value="elettrico" {{ old('fuel_type') == 'elettrico' ? 'selected' : '' }}>
                                {{ __('Elettrico') }}</option>
                            <option value="ibrido" {{ old('fuel_type') == 'ibrido' ? 'selected' : '' }}>
                                {{ __('Ibrido') }}</option>
                        </select>
                        @error('fuel_type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="vehicle_type_id">{{ __('Tipologia') }} <span class="req">*</span></label>
                        <select class="select @error('vehicle_type_id') is-invalid @enderror" id="vehicle_type_id"
                            name="vehicle_type_id" required>
                            <option value="" disabled selected>{{ __('Seleziona una tipologia') }}</option>
                            @foreach ($vehicleTypes as $type)
                                <option value="{{ $type->id }}"
                                    {{ old('vehicle_type_id') == $type->id ? 'selected' : '' }}>{{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_type_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row2">
                    <x-form.date-input name="immatricolation_date" label="{{ __('Data immatricolazione') }}" required />
                    <div class="field">
                        <label for="registration_card">{{ __('Carta di circolazione') }}</label>
                        <label class="file-drop" for="registration_card" id="registration_card_label">
                            <i class="fa-solid fa-file-arrow-up"></i> {{ __('Clicca per caricare (PDF, JPG, PNG)') }}
                        </label>
                        <input type="file" class="@error('registration_card') is-invalid @enderror" id="registration_card"
                            name="registration_card" accept=".pdf,.jpg,.jpeg,.png" hidden>
                        @error('registration_card')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Sezione 2: Garanzia --}}
            <div class="form-section">
                <h2><span class="num">2</span> {{ __('Garanzia') }}</h2>
                <div class="row2">
                    <x-form.date-input name="warranty_expiration_date" label="{{ __('Data di scadenza originale') }}"
                        :required="old('has_warranty_extension')" />
                    <div class="field">
                        <label for="warranty_extension_duration">{{ __('Durata estensione (mesi)') }}</label>
                        <input type="number" class="input @error('warranty_extension_duration') is-invalid @enderror"
                            id="warranty_extension_duration" name="warranty_extension_duration"
                            value="{{ old('warranty_extension_duration') }}" placeholder="{{ __('es. 24') }}"
                            @if (old('has_warranty_extension')) required @endif>
                        @error('warranty_extension_duration')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <input type="hidden" name="has_warranty_extension" value="0">
                <label class="check">
                    <input type="checkbox" id="has_warranty_extension" name="has_warranty_extension" value="1"
                        {{ old('has_warranty_extension') ? 'checked' : '' }}>
                    <div>
                        <div class="label">{{ __('Estensione garanzia') }}</div>
                        <div class="sub">{{ __('Aggiungi mesi alla scadenza originale') }}</div>
                    </div>
                </label>
                @error('has_warranty_extension')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Sezione 3: Assicurazione --}}
            <div class="form-section">
                <h2><span class="num">3</span> {{ __('Assicurazione') }}</h2>
                <x-form.month-input name="insurance_due_date" label="{{ __('Data di scadenza') }}" />
            </div>

            {{-- Sezione 4: Cinghia distribuzione --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">4</span> {{ __('Cinghia distribuzione') }}</h2>
                <label class="check">
                    <input type="checkbox" value="1" id="has_timing_belt" name="has_timing_belt"
                        {{ old('has_timing_belt') ? 'checked' : '' }}>
                    <div>
                        <div class="label">{{ __('Veicolo dotato di cinghia di distribuzione') }}</div>
                        <div class="sub">
                            {{ __('Se attivo, potrai creare una scadenza "Cinghia Distribuzione" che scatta dopo 100.000 km o 10 anni.') }}
                        </div>
                    </div>
                </label>
            </div>

            <div class="form-actions">
                <button id="vehicle-submit-btn" type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Aggiungi veicolo') }}
                </button>
                <a href="{{ request('back', route('admin.vehicles.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const licensePlateInput = document.getElementById('license_plate');
            const warrantyExtensionCheckbox = document.getElementById('has_warranty_extension');
            const warrantyExpirationDateInput = document.getElementById('warranty_expiration_date');
            const warrantyExtensionDurationInput = document.getElementById('warranty_extension_duration');
            const registrationCardInput = document.getElementById('registration_card');
            const registrationCardLabel = document.getElementById('registration_card_label');
            const registrationCardDefaultText = registrationCardLabel.innerHTML;

            // Mantiene lato client il formato targa coerente con le regole server.
            function uppercaseLicensePlate() {
                licensePlateInput.value = licensePlateInput.value.toUpperCase().replace(/\s+/g, '');
            }

            // I campi garanzia diventano obbligatori solo con estensione attiva.
            function toggleWarrantyRequiredFields() {
                const isChecked = warrantyExtensionCheckbox.checked;

                warrantyExpirationDateInput.required = isChecked;
                warrantyExtensionDurationInput.required = isChecked;
            }

            // Mostra il nome del file selezionato nella zona di upload.
            function updateRegistrationCardLabel() {
                if (registrationCardInput.files.length > 0) {
                    registrationCardLabel.textContent = registrationCardInput.files[0].name;
                } else {
                    registrationCardLabel.innerHTML = registrationCardDefaultText;
                }
            }

            toggleWarrantyRequiredFields();
            uppercaseLicensePlate();
            warrantyExtensionCheckbox.addEventListener('change', toggleWarrantyRequiredFields);
            licensePlateInput.addEventListener('input', uppercaseLicensePlate);
            registrationCardInput.addEventListener('change', updateRegistrationCardLabel);
        });
    </script>
@endsection
