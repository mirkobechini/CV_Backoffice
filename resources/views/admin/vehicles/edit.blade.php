@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.vehicles.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica veicolo</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="vehicle-edit-form" method="POST" action="{{ route('admin.vehicles.update', $vehicle->id) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli veicolo</h2>
                        <div class="mb-3">
                            <label for="license_plate" class="form-label">Targa</label>
                            <input type="text" class="form-control @error('license_plate') is-invalid @enderror"
                                id="license_plate" name="license_plate"
                                value="{{ old('license_plate', $vehicle->license_plate) }}"
                                style="text-transform: uppercase;" required>
                            @error('license_plate')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <!-- Sezione dinamica con Livewire per marca e modello -->
                        @livewire('vehicle-select', [
                            'brand_id' => old('brand_id', $vehicle->brand_id),
                            'car_model_id' => old('car_model_id', $vehicle->car_model_id),
                        ])
                        <!-- Fine sezione dinamica con Livewire per marca e modello -->
                        <div class="mb-3">
                            <label for="fuel_type" class="form-label">Carburante</label>
                            <select class="form-select @error('fuel_type') is-invalid @enderror" id="fuel_type"
                                name="fuel_type" value="{{ old('fuel_type', $vehicle->fuel_type) }}">
                                <option value="benzina"
                                    {{ old('fuel_type', $vehicle->fuel_type) == 'benzina' ? 'selected' : '' }}>Benzina
                                </option>
                                <option value="diesel"
                                    {{ old('fuel_type', $vehicle->fuel_type) == 'diesel' ? 'selected' : '' }}>Diesel
                                </option>
                                <option value="elettrico"
                                    {{ old('fuel_type', $vehicle->fuel_type) == 'elettrico' ? 'selected' : '' }}>Elettrico
                                </option>
                                <option value="ibrido"
                                    {{ old('fuel_type', $vehicle->fuel_type) == 'ibrido' ? 'selected' : '' }}>Ibrido
                                </option>
                            </select>
                            @error('fuel_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="vehicle_type_id" class="form-label">Tipologia</label>
                            <select class="form-select @error('vehicle_type_id') is-invalid @enderror" id="vehicle_type_id"
                                name="vehicle_type_id" required>
                                @foreach ($vehicleTypes as $type)
                                    <option value="{{ $type->id }}"
                                        {{ old('vehicle_type_id', $vehicle->vehicle_type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="internal_code" class="form-label">Sigla</label>
                            <input type="number" class="form-control @error('internal_code') is-invalid @enderror"
                                id="internal_code" name="internal_code"
                                value="{{ old('internal_code', $vehicle->internal_code) }}">
                            @error('internal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <x-form.date-input name="immatricolation_date" label="Data immatricolazione" :model="$vehicle"
                            required />
                        <div class="mb-3">
                            <label for="registration_card" class="form-label">Carta di circolazione</label>
                            <input type="file" class="form-control @error('registration_card') is-invalid @enderror"
                                id="registration_card" name="registration_card" accept=".pdf,.jpg,.jpeg,.png">
                            @error('registration_card')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <section class="mb-3 mt-2 row gap-4 ">
                        <h3>Dettagli aggiuntivi</h3>
                        <section class="col mb-3 card p-3">
                            <h4>Garanzia</h4>
                            <x-form.date-input name="warranty_expiration_date" label="Data di scadenza originale"
                                :value="$warrantyOriginalExpirationDate" :required="old('has_warranty_extension', $vehicle->has_warranty_extension)" />
                            <div class="mb-3">
                                <div class="form-check">
                                    <label for="has_warranty_extension" class="form-check-label">Estensione
                                        garanzia</label>
                                    <input type="hidden" name="has_warranty_extension" value="0">
                                    <input type="checkbox"
                                        class="form-check-input @error('has_warranty_extension') is-invalid @enderror"
                                        id="has_warranty_extension" name="has_warranty_extension" value="1"
                                        {{ old('has_warranty_extension', $vehicle->has_warranty_extension) ? 'checked' : '' }}>
                                    @error('has_warranty_extension')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div>
                                    <label for="warranty_extension_duration" class="form-label">Durata estensione
                                        (mesi)</label>
                                    <input type="number"
                                        class="form-control @error('warranty_extension_duration') is-invalid @enderror"
                                        id="warranty_extension_duration" name="warranty_extension_duration"
                                        value="{{ old('warranty_extension_duration', $vehicle->warranty_extension_duration) }}"
                                        @if (old('has_warranty_extension', $vehicle->has_warranty_extension)) required @endif>
                                    @error('warranty_extension_duration')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </section>
                        <!-- Sezione assicurazione
                            <section class="col mb-3 card p-3">
                                <h4>Assicurazione</h4>
                                <x-form.month-input name="insurance_due_date" label="Data di scadenza" :value="$vehicle->insurance_due_date" />
                            </section>
                            -->
                    </section>
                    <button id="vehicle-edit-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva modifiche</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const licensePlateInput = document.getElementById('license_plate');
            const warrantyExtensionCheckbox = document.getElementById('has_warranty_extension');
            const warrantyExpirationDateInput = document.getElementById('warranty_expiration_date');
            const warrantyExtensionDurationInput = document.getElementById('warranty_extension_duration');

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

            toggleWarrantyRequiredFields();
            uppercaseLicensePlate();
            warrantyExtensionCheckbox.addEventListener('change', toggleWarrantyRequiredFields);
            licensePlateInput.addEventListener('input', uppercaseLicensePlate);

        });
    </script>
@endsection
