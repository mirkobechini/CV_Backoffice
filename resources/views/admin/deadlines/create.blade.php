@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Scadenze'), 'url' => route('admin.deadlines.index')],
        ['label' => __('Nuova scadenza')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova scadenza') }}</h1>
        <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="deadline-form" method="POST" action="{{ route('admin.deadlines.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Dettagli scadenza --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli scadenza') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                        <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                            name="vehicle_id" required>
                            <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                    data-needs-oxygen-check="{{ $vehicle->vehicleType?->needs_oxygen_check ? '1' : '0' }}"
                                    {{ old('vehicle_id', $selectedVehicleId) == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->internal_code }} · {{ $vehicle->brand?->name ?? 'N/A' }}
                                    {{ $vehicle->carModel?->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="type">{{ __('Tipologia') }} <span class="req">*</span></label>
                        <select class="select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="" disabled selected>{{ __('Seleziona una tipologia') }}</option>
                            <option value="Assicurazione" {{ old('type') == 'Assicurazione' ? 'selected' : '' }}>
                                {{ __('Assicurazione') }}</option>
                            <option value="Revisione Ministeriale"
                                {{ old('type') == 'Revisione Ministeriale' ? 'selected' : '' }}>
                                {{ __('Revisione Ministeriale') }}</option>
                            <option id="oxygen-type-option" value="Revisione Impianto Ossigeno"
                                {{ old('type') == 'Revisione Impianto Ossigeno' ? 'selected' : '' }}>
                                {{ __('Revisione Impianto Ossigeno') }}</option>
                            <option value="Tagliando" {{ old('type') == 'Tagliando' ? 'selected' : '' }}>
                                {{ __('Tagliando') }}</option>
                            <option value="Cinghia Distribuzione"
                                {{ old('type') == 'Cinghia Distribuzione' ? 'selected' : '' }}>
                                {{ __('Cinghia Distribuzione') }}</option>
                        </select>
                        @error('type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div id="due-date-group">
                    <x-form.month-input name="due_date" id="due_date" label="{{ __('Data di scadenza') }}" />
                    <div class="hint">
                        {{ __('Per "Revisione Ministeriale" e "Revisione Impianto Ossigeno" la data viene calcolata automaticamente.') }}
                    </div>
                </div>
            </div>

            {{-- Sezione 2: Impostazioni km (visibile per Tagliando/Cinghia; per le
            revisioni si mostra solo il km facoltativo di riferimento) --}}
            <div class="form-section" id="km-settings-group" style="display:none;">
                <h2><span class="num">2</span> {{ __('Chilometraggio') }}</h2>
                <div class="km-box">
                    <div class="title"><span class="ic"><i class="fa-solid fa-gear"></i></span>
                        <span id="km-settings-title">{{ __('Scadenza per km e data') }}</span>
                    </div>
                    <div class="row3">
                        <div class="field" id="interval-km-field">
                            <label for="interval_km">{{ __('Intervallo km') }}</label>
                            <input type="number" class="input @error('interval_km') is-invalid @enderror"
                                id="interval_km" name="interval_km" value="{{ old('interval_km') }}" min="0"
                                placeholder="es. 15000">
                            @error('interval_km')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="field">
                            <label for="last_mileage" id="last-mileage-label">{{ __("Km all'ultimo cambio") }}</label>
                            <input type="number" class="input @error('last_mileage') is-invalid @enderror"
                                id="last_mileage" name="last_mileage" value="{{ old('last_mileage') }}" min="0"
                                placeholder="es. 50000">
                            @error('last_mileage')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="field" id="interval-days-field">
                            <label for="interval_days">{{ __('Intervallo giorni') }}</label>
                            <input type="number" class="input @error('interval_days') is-invalid @enderror"
                                id="interval_days" name="interval_days" value="{{ old('interval_days') }}" min="0"
                                placeholder="es. 365">
                            @error('interval_days')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="hint" id="km-settings-hint">
                        {{ __('La scadenza scatta al primo tra superamento km o raggiungimento data. Per la cinghia distribuzione: 100.000 km o 10 anni (3650 giorni).') }}
                    </div>
                </div>
            </div>

            {{-- Sezione 3: Stato --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">3</span> {{ __('Stato') }}</h2>
                <label class="check">
                    <input type="checkbox" value="1" id="is_renewed" name="is_renewed"
                        {{ old('is_renewed') ? 'checked' : '' }}>
                    <div>
                        <div class="label">{{ __('Segna subito come rinnovata') }}</div>
                        <div class="sub">{{ __('Se la scadenza è già stata effettuata') }}</div>
                    </div>
                </label>
                @error('is_renewed')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <button id="issue-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva scadenza') }}
                </button>
                <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicle_id');
            const typeSelect = document.getElementById('type');
            const oxygenOption = document.getElementById('oxygen-type-option');
            const dueDateGroup = document.getElementById('due-date-group');
            // Flatpickr sposta l'id "due_date" sull'altInput visibile: per
            // svuotare/disabilitare il valore reale che viene inviato dobbiamo
            // usare il campo originale (nascosto), individuabile per name.
            const dueDateAlt = document.getElementById('due_date');
            const dueDateReal = document.querySelector('input[name="due_date"]');
            const kmSettingsGroup = document.getElementById('km-settings-group');
            const intervalKmField = document.getElementById('interval-km-field');
            const intervalDaysField = document.getElementById('interval-days-field');
            const kmSettingsTitle = document.getElementById('km-settings-title');
            const lastMileageLabel = document.getElementById('last-mileage-label');
            const kmSettingsHint = document.getElementById('km-settings-hint');
            const ministerialType = 'Revisione Ministeriale';
            const oxygenType = 'Revisione Impianto Ossigeno';
            const kmTypes = ['Tagliando', 'Cinghia Distribuzione'];

            // Abilita revisione ossigeno solo per tipologie mezzo che la prevedono.
            const selectedVehicleNeedsOxygenCheck = () => {
                const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];

                if (!selectedOption) {
                    return false;
                }

                return selectedOption.getAttribute('data-needs-oxygen-check') === '1';
            };

            const syncOxygenTypeAvailability = () => {
                const canUseOxygenType = selectedVehicleNeedsOxygenCheck();
                oxygenOption.disabled = !canUseOxygenType;

                if (!canUseOxygenType && typeSelect.value === oxygenType) {
                    typeSelect.value = '';
                }
            };

            // La data manuale è richiesta solo per scadenze non auto-calcolate.
            const toggleVisibility = () => {
                const isAutoCalculated = [ministerialType, oxygenType].includes(typeSelect.value);
                const isKmType = kmTypes.includes(typeSelect.value);

                dueDateGroup.style.display = isAutoCalculated ? 'none' : '';
                dueDateReal.disabled = isAutoCalculated;
                if (dueDateAlt) {
                    dueDateAlt.disabled = isAutoCalculated;
                }

                // Per Tagliando/Cinghia servono intervallo km/giorni per calcolare
                // la prossima scadenza; per le revisioni (data auto-calcolata) si
                // mostra solo il km facoltativo come semplice annotazione.
                kmSettingsGroup.style.display = (isKmType || isAutoCalculated) ? '' : 'none';
                intervalKmField.style.display = isKmType ? '' : 'none';
                intervalDaysField.style.display = isKmType ? '' : 'none';

                if (isAutoCalculated) {
                    kmSettingsTitle.textContent = '{{ __('Km alla revisione (facoltativo)') }}';
                    lastMileageLabel.textContent = '{{ __('Km rilevati alla revisione') }}';
                    kmSettingsHint.textContent = '{{ __('Annotazione facoltativa: il chilometraggio del veicolo al momento di questa revisione, solo per riferimento.') }}';

                    if (dueDateReal._flatpickr) {
                        dueDateReal._flatpickr.clear();
                    } else {
                        dueDateReal.value = '';
                    }
                } else {
                    kmSettingsTitle.textContent = '{{ __('Scadenza per km e data') }}';
                    lastMileageLabel.textContent = '{{ __("Km all'ultimo cambio") }}';
                    kmSettingsHint.textContent = '{{ __('La scadenza scatta al primo tra superamento km o raggiungimento data. Per la cinghia distribuzione: 100.000 km o 10 anni (3650 giorni).') }}';
                }
            };

            syncOxygenTypeAvailability();
            toggleVisibility();
            vehicleSelect.addEventListener('change', () => {
                syncOxygenTypeAvailability();
                toggleVisibility();
            });
            typeSelect.addEventListener('change', toggleVisibility);
        });
    </script>
@endsection
