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

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert danger">{{ session('error') }}</div>
    @endif

    {{-- Form nascosto usato solo per inviare la scansione: i file caricati
    sono quelli del fronte/retro libretto qui sotto (copiati via JS, vedi
    script in fondo), così non ci sono selettori file duplicati. --}}
    <form id="scan-libretto-form" method="POST" action="{{ route('admin.vehicles.scan-libretto') }}"
        enctype="multipart/form-data" style="display:none;">
        @csrf
        <input type="file" name="photo_front" id="scan_photo_front_hidden">
        <input type="file" name="photo_back" id="scan_photo_back_hidden">
    </form>

    <div class="form-card">
        <form id="vehicle-form" method="POST" action="{{ route('admin.vehicles.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf
            @if (session('scanned_registration_card_path'))
                <input type="hidden" name="scanned_registration_card_path"
                    value="{{ session('scanned_registration_card_path') }}">
            @endif

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
                @if (session('unmatched_brand_text'))
                    <div class="hint">{{ __('Marca letta dal libretto (da selezionare a mano): :text', ['text' => session('unmatched_brand_text')]) }}</div>
                @endif
                @if (session('unmatched_model_text'))
                    <div class="hint">{{ __('Modello letto dal libretto (da selezionare a mano): :text', ['text' => session('unmatched_model_text')]) }}</div>
                @endif

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
                        <label for="registration_card">{{ __('Libretto — fronte (carta di circolazione)') }}</label>
                        <label class="file-drop" for="registration_card" id="registration_card_label">
                            @if (session('scanned_registration_card_path'))
                                <i class="fa-solid fa-file-circle-check"></i>
                                {{ __('Foto scansionata già allegata · clicca per sostituirla') }}
                            @else
                                <i class="fa-solid fa-file-arrow-up"></i>
                                {{ __('Clicca per caricare (PDF, JPG, PNG, HEIC)') }}
                            @endif
                        </label>
                        <input type="file" class="@error('registration_card') is-invalid @enderror" id="registration_card"
                            name="registration_card" accept=".pdf,.jpg,.jpeg,.png,.heic,.heif" hidden>
                        @error('registration_card')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @error('photo_front')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="registration_card_back">{{ __('Libretto — retro') }}</label>
                        <label class="file-drop" for="registration_card_back" id="registration_card_back_label">
                            <i class="fa-solid fa-file-arrow-up"></i> {{ __('Clicca per caricare (JPG, PNG, HEIC)') }}
                        </label>
                        <input type="file" id="registration_card_back" accept=".jpg,.jpeg,.png,.heic,.heif" hidden>
                        @error('photo_back')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="hint">{{ __('Serve solo per la scansione (timbri di revisione): non viene salvata.') }}</div>
                    </div>
                    <div class="field">
                        <label>&nbsp;</label>
                        <button type="button" id="scan-libretto-btn" class="btn" disabled
                            data-loading-text="{{ __('Scansione in corso...') }}">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Compila automaticamente con AI') }}
                        </button>
                        <div class="hint">
                            {{ __('Carica fronte e retro del libretto per abilitare la lettura automatica dei dati: verifica sempre prima di salvare.') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sezione 2: Specifiche tecniche (dal libretto, tutte facoltative) --}}
            <div class="form-section">
                <h2><span class="num">2</span> {{ __('Specifiche tecniche') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="vin">{{ __('Numero di telaio (VIN)') }}</label>
                        <input type="text" class="input @error('vin') is-invalid @enderror" id="vin" name="vin"
                            value="{{ old('vin') }}">
                        @error('vin')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="color">{{ __('Colore') }}</label>
                        <input type="text" class="input @error('color') is-invalid @enderror" id="color" name="color"
                            value="{{ old('color') }}">
                        @error('color')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="seats">{{ __('Numero posti') }}</label>
                        <input type="number" class="input @error('seats') is-invalid @enderror" id="seats"
                            name="seats" value="{{ old('seats') }}" min="1" max="99">
                        @error('seats')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="vehicle_category">{{ __('Categoria veicolo') }}</label>
                        <input type="text" class="input @error('vehicle_category') is-invalid @enderror"
                            id="vehicle_category" name="vehicle_category" value="{{ old('vehicle_category') }}"
                            placeholder="{{ __('es. M1') }}">
                        @error('vehicle_category')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="environmental_class">{{ __('Classe ambientale') }}</label>
                        <input type="text" class="input @error('environmental_class') is-invalid @enderror"
                            id="environmental_class" name="environmental_class"
                            value="{{ old('environmental_class') }}" placeholder="{{ __('es. Euro 6') }}">
                        @error('environmental_class')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="max_mass_kg">{{ __('Massa massima ammissibile (kg)') }}</label>
                        <input type="number" class="input @error('max_mass_kg') is-invalid @enderror"
                            id="max_mass_kg" name="max_mass_kg" value="{{ old('max_mass_kg') }}" min="0">
                        @error('max_mass_kg')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="engine_displacement_cc">{{ __('Cilindrata (cc)') }}</label>
                        <input type="number" class="input @error('engine_displacement_cc') is-invalid @enderror"
                            id="engine_displacement_cc" name="engine_displacement_cc"
                            value="{{ old('engine_displacement_cc') }}" min="0">
                        @error('engine_displacement_cc')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="engine_power_kw">{{ __('Potenza (kW)') }}</label>
                        <input type="number" class="input @error('engine_power_kw') is-invalid @enderror"
                            id="engine_power_kw" name="engine_power_kw" value="{{ old('engine_power_kw') }}"
                            min="0">
                        @error('engine_power_kw')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field" data-tire-size-group>
                    <label>{{ __('Misure pneumatici consigliate') }}</label>
                    <div class="hint" style="margin-top:0;margin-bottom:8px;">
                        {{ __('Un veicolo può averne più di una (es. assale anteriore diverso dal posteriore).') }}
                    </div>
                    <div data-tire-size-container>
                        @foreach (old('allowed_tire_sizes', []) as $i => $size)
                            <x-form.tire-size-input name="allowed_tire_sizes[]" id="allowed_tire_sizes_{{ $i }}"
                                :value="$size" label="{{ __('Misura pneumatici') }}" removable />
                        @endforeach
                    </div>
                    <button type="button" class="btn ghost" data-tire-size-add>
                        <i class="fa-solid fa-plus"></i> {{ __("Aggiungi un'altra misura") }}
                    </button>
                    <template data-tire-size-template>
                        <x-form.tire-size-input name="allowed_tire_sizes[]" id="allowed_tire_sizes_new"
                            label="{{ __('Misura pneumatici') }}" removable />
                    </template>
                </div>
            </div>

            {{-- Sezione 3: Garanzia --}}
            <div class="form-section">
                <h2><span class="num">3</span> {{ __('Garanzia') }}</h2>
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

            {{-- Sezione 4: Assicurazione --}}
            <div class="form-section">
                <h2><span class="num">4</span> {{ __('Assicurazione') }}</h2>
                <x-form.month-input name="insurance_due_date" label="{{ __('Data di scadenza') }}" />
            </div>

            {{-- Sezione 5: Distribuzione --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">5</span> {{ __('Distribuzione') }}
                    @if (session('timing_belt_suggested'))
                        <span class="badge b-amber" title="{{ __('Stima basata su marca/modello: verifica prima di salvare.') }}">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Suggerito dall\'AI — verifica') }}
                        </span>
                    @endif
                </h2>
                <div class="row2">
                    <label class="check">
                        <input type="radio" value="1" id="has_timing_belt_cinghia" name="has_timing_belt"
                            {{ old('has_timing_belt', '0') == '1' ? 'checked' : '' }}>
                        <div>
                            <div class="label">{{ __('Cinghia di distribuzione') }}</div>
                            <div class="sub">
                                {{ __('Genera una scadenza "Cinghia Distribuzione" dopo 100.000 km o 10 anni.') }}
                            </div>
                        </div>
                    </label>
                    <label class="check">
                        <input type="radio" value="0" id="has_timing_belt_catena" name="has_timing_belt"
                            {{ old('has_timing_belt', '0') == '0' ? 'checked' : '' }}>
                        <div>
                            <div class="label">{{ __('Catena di distribuzione') }}</div>
                            <div class="sub">{{ __('Nessuna scadenza: la catena non richiede sostituzioni periodiche.') }}</div>
                        </div>
                    </label>
                </div>
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
            const registrationCardBackInput = document.getElementById('registration_card_back');
            const registrationCardBackLabel = document.getElementById('registration_card_back_label');
            const registrationCardBackDefaultText = registrationCardBackLabel.innerHTML;
            const scanBtn = document.getElementById('scan-libretto-btn');
            const scanForm = document.getElementById('scan-libretto-form');
            const scanPhotoFrontInput = document.getElementById('scan_photo_front_hidden');
            const scanPhotoBackInput = document.getElementById('scan_photo_back_hidden');

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

            function updateRegistrationCardBackLabel() {
                if (registrationCardBackInput.files.length > 0) {
                    registrationCardBackLabel.textContent = registrationCardBackInput.files[0].name;
                } else {
                    registrationCardBackLabel.innerHTML = registrationCardBackDefaultText;
                }
            }

            // La scansione richiede fronte E retro (i dati anagrafici sono
            // sul fronte, i timbri di revisione sul retro): il pulsante
            // resta disabilitato finché non sono stati caricati entrambi.
            function updateScanButtonState() {
                scanBtn.disabled = !(registrationCardInput.files.length && registrationCardBackInput.files.length);
            }

            // Invia allo scan gli stessi file scelti per fronte/retro, senza
            // selettori duplicati: li copia nel form nascosto tramite
            // DataTransfer e lo invia.
            scanBtn.addEventListener('click', () => {
                if (scanBtn.disabled) {
                    return;
                }

                const frontTransfer = new DataTransfer();
                frontTransfer.items.add(registrationCardInput.files[0]);
                scanPhotoFrontInput.files = frontTransfer.files;

                const backTransfer = new DataTransfer();
                backTransfer.items.add(registrationCardBackInput.files[0]);
                scanPhotoBackInput.files = backTransfer.files;

                scanBtn.disabled = true;
                scanBtn.innerHTML = scanBtn.dataset.loadingText;
                scanForm.submit();
            });

            toggleWarrantyRequiredFields();
            uppercaseLicensePlate();
            warrantyExtensionCheckbox.addEventListener('change', toggleWarrantyRequiredFields);
            licensePlateInput.addEventListener('input', uppercaseLicensePlate);
            registrationCardInput.addEventListener('change', () => {
                updateRegistrationCardLabel();
                updateScanButtonState();
            });
            registrationCardBackInput.addEventListener('change', () => {
                updateRegistrationCardBackLabel();
                updateScanButtonState();
            });
        });
    </script>
@endsection
