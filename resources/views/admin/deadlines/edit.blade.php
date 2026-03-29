@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica scadenza</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="deadline-form" method="POST" action="{{ route('admin.deadlines.update', $deadline) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
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
                                        {{ old('vehicle_id', $deadline->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand }} {{ $vehicle->model }}
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
                                <option value="Assicurazione"
                                    {{ old('type', $deadline->type) == 'Assicurazione' ? 'selected' : '' }}>Assicurazione
                                </option>
                                <option value="Revisione Ministeriale"
                                    {{ old('type', $deadline->type) == 'Revisione Ministeriale' ? 'selected' : '' }}>
                                    Revisione Ministeriale</option>
                                <option id="oxygen-type-option" value="Revisione Impianto Ossigeno"
                                    {{ old('type', $deadline->type) == 'Revisione Impianto Ossigeno' ? 'selected' : '' }}>
                                    Revisione Impianto Ossigeno</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3" id="due-date-group">
                            <x-form.month-input name="due_date" id="due_date" label="Data di scadenza" :model="$deadline"
                                :value="$deadline->due_date" />
                            <small class="text-muted">Per "Revisione Ministeriale" e "Revisione Impianto Ossigeno"
                                la data viene calcolata automaticamente. La revisione ossigeno è disponibile solo per
                                le ambulanze.</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check mt-2">
                                <input class="form-check-input @error('is_renewed') is-invalid @enderror"
                                    type="checkbox" value="1" id="is_renewed" name="is_renewed"
                                    {{ old('is_renewed', $deadline->status === 'renewed') ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_renewed">
                                    Segna come rinnovata
                                </label>
                            </div>
                            @error('is_renewed')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="issue-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva modifiche</button>
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
            const ministerialType = 'Revisione Ministeriale';
            const oxygenType = 'Revisione Impianto Ossigeno';

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
            const toggleDueDateVisibility = () => {
                const isAutoCalculated = [ministerialType, oxygenType].includes(typeSelect.value);

                if (isAutoCalculated) {
                    dueDateGroup.style.display = 'none';
                    dueDateInput.disabled = true;
                    dueDateInput.value = '';
                } else {
                    dueDateGroup.style.display = '';
                    dueDateInput.disabled = false;
                }
            };

            syncOxygenTypeAvailability();
            toggleDueDateVisibility();
            vehicleSelect.addEventListener('change', () => {
                syncOxygenTypeAvailability();
                toggleDueDateVisibility();
            });
            typeSelect.addEventListener('change', toggleDueDateVisibility);
        });
    </script>
@endsection
