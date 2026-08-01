@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Aggiungi nuova scadenza</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="deadline-form" method="POST" action="{{ route('admin.deadlines.store') }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    <section class="mb-3 row">
                        <h2>Dettagli Scadenza</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        data-needs-oxygen-check="{{ $vehicle->vehicleType?->needs_oxygen_check ? '1' : '0' }}"
                                        {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand?->name ?? 'N/A' }}
                                        {{ $vehicle->carModel?->name ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Tipologia</label>
                            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type"
                                required>
                                <option value="">Seleziona una tipologia</option>
                                <option value="Assicurazione" {{ old('type') == 'Assicurazione' ? 'selected' : '' }}>
                                    Assicurazione</option>
                                <option value="Revisione Ministeriale"
                                    {{ old('type') == 'Revisione Ministeriale' ? 'selected' : '' }}>Revisione Ministeriale
                                </option>
                                <option id="oxygen-type-option" value="Revisione Impianto Ossigeno"
                                    {{ old('type') == 'Revisione Impianto Ossigeno' ? 'selected' : '' }}>Revisione Impianto
                                    Ossigeno</option>
                                <option value="Tagliando" {{ old('type') == 'Tagliando' ? 'selected' : '' }}>Tagliando
                                </option>
                                <option value="Cinghia Distribuzione"
                                    {{ old('type') == 'Cinghia Distribuzione' ? 'selected' : '' }}>Cinghia Distribuzione
                                </option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3" id="due-date-group">
                            <x-form.month-input name="due_date" id="due_date" label="Data di scadenza" />
                            <small class="text-muted">Per "Revisione Ministeriale" e "Revisione Impianto Ossigeno"
                                la data viene calcolata automaticamente.</small>
                        </div>
                        <div class="mb-3 border rounded p-3 bg-body-secondary" id="km-settings-group"
                            style="display: none;">
                            <h3 class="h6">Impostazioni km</h3>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label for="interval_km" class="form-label">Intervallo km</label>
                                    <input type="number" class="form-control @error('interval_km') is-invalid @enderror"
                                        id="interval_km" name="interval_km" value="{{ old('interval_km') }}" min="0"
                                        placeholder="es. 15000">
                                    @error('interval_km')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="last_mileage" class="form-label">Km all'ultimo cambio</label>
                                    <input type="number" class="form-control @error('last_mileage') is-invalid @enderror"
                                        id="last_mileage" name="last_mileage" value="{{ old('last_mileage') }}"
                                        min="0" placeholder="es. 50000">
                                    @error('last_mileage')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="interval_days" class="form-label">Intervallo giorni</label>
                                    <input type="number" class="form-control @error('interval_days') is-invalid @enderror"
                                        id="interval_days" name="interval_days" value="{{ old('interval_days') }}"
                                        min="0" placeholder="es. 365">
                                    @error('interval_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <small class="text-muted">La scadenza scatta al primo tra superamento km o raggiungimento data.
                                Per la cinghia distribuzione: 100.000 km o 10 anni (3650 giorni).</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check mt-2">
                                <input class="form-check-input @error('is_renewed') is-invalid @enderror" type="checkbox"
                                    value="1" id="is_renewed" name="is_renewed"
                                    {{ old('is_renewed') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_renewed">
                                    Segna subito come rinnovata
                                </label>
                            </div>
                            @error('is_renewed')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="issue-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicle_id');
            const typeSelect = document.getElementById('type');
            const oxygenOption = document.getElementById('oxygen-type-option');
            const dueDateGroup = document.getElementById('due-date-group');
            const dueDateInput = document.getElementById('due_date');
            const kmSettingsGroup = document.getElementById('km-settings-group');
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
                dueDateInput.disabled = isAutoCalculated;

                kmSettingsGroup.style.display = isKmType ? '' : 'none';

                if (isAutoCalculated) {
                    dueDateInput.value = '';
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
