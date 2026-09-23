@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => __('Nuova attrezzatura')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova attrezzatura') }}</h1>
        <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="equipment-form" method="POST" action="{{ route('admin.equipments.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Dettagli attrezzatura --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli attrezzatura') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name') }}" placeholder="{{ __('es. Estintore') }}" required>
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="equipment_type_id">{{ __('Tipo di attrezzatura') }} <span class="req">*</span></label>
                        <select class="select @error('equipment_type_id') is-invalid @enderror" id="equipment_type_id"
                            name="equipment_type_id" required>
                            <option value="" disabled {{ old('equipment_type_id', $selectedEquipmentTypeId) ? '' : 'selected' }}>
                                {{ __('Seleziona un tipo di attrezzatura') }}</option>
                            @foreach ($equipmentTypes as $type)
                                <option value="{{ $type->id }}" data-category="{{ $type->category }}"
                                    {{ old('equipment_type_id', $selectedEquipmentTypeId) == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('equipment_type_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="brand">{{ __('Marca') }}</label>
                        <input type="text" class="input @error('brand') is-invalid @enderror" id="brand" name="brand"
                            value="{{ old('brand') }}">
                        @error('brand')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="model">{{ __('Modello') }}</label>
                        <input type="text" class="input @error('model') is-invalid @enderror" id="model" name="model"
                            value="{{ old('model') }}">
                        @error('model')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="serial_number">{{ __('Numero di serie / matricola') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('serial_number') is-invalid @enderror"
                            id="serial_number" name="serial_number" value="{{ old('serial_number') }}"
                            placeholder="{{ __('es. SN-8821') }}" required>
                        @error('serial_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="identification_number">{{ __('Numero identificativo') }}</label>
                        <input type="text" class="input @error('identification_number') is-invalid @enderror"
                            id="identification_number" name="identification_number"
                            value="{{ old('identification_number') }}">
                        @error('identification_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <x-form.date-input name="fabrication_date" label="{{ __('Data di fabbricazione') }}" />
                    <x-form.date-input name="revision_date" label="{{ __('Data revisione') }}" />
                </div>
                <div class="row2">
                    <x-form.month-input name="expiration_date" label="{{ __('Data scadenza') }}" />
                </div>

                {{-- Campi specifici estintori --}}
                <div id="extinguisher-fields" style="display:none;">
                    <div class="row2">
                        <div class="field">
                            <label for="extinguisher_agent">{{ __('Agente estinguente') }}</label>
                            <select class="select @error('extinguisher_agent') is-invalid @enderror"
                                id="extinguisher_agent" name="extinguisher_agent">
                                <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                                <option value="co2" {{ old('extinguisher_agent') == 'co2' ? 'selected' : '' }}>
                                    {{ __('CO2') }}</option>
                                <option value="powder" {{ old('extinguisher_agent') == 'powder' ? 'selected' : '' }}>
                                    {{ __('Polvere') }}</option>
                            </select>
                            @error('extinguisher_agent')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="field">
                            <label for="weight_kg">{{ __('Peso (kg)') }}</label>
                            <input type="number" step="0.01" class="input @error('weight_kg') is-invalid @enderror"
                                id="weight_kg" name="weight_kg" value="{{ old('weight_kg') }}" min="0">
                            @error('weight_kg')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="row2">
                        <x-form.date-input name="collaudo_date" label="{{ __('Data collaudo') }}" />
                        <x-form.month-input name="next_collaudo_date" label="{{ __('Prossimo collaudo') }}" />
                    </div>
                </div>

                {{-- Campi specifici sedie/barelle --}}
                <div id="chair-stretcher-fields" style="display:none;">
                    <div class="row2">
                        <div class="field" id="chair-type-field">
                            <label for="chair_type">{{ __('Tipo di sedia') }}</label>
                            <select class="select @error('chair_type') is-invalid @enderror" id="chair_type"
                                name="chair_type">
                                <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                                <option value="electric" {{ old('chair_type') == 'electric' ? 'selected' : '' }}>
                                    {{ __('Elettrica') }}</option>
                                <option value="manual_2_wheel"
                                    {{ old('chair_type') == 'manual_2_wheel' ? 'selected' : '' }}>
                                    {{ __('Manuale a 2 ruote') }}</option>
                                <option value="manual_4_wheel"
                                    {{ old('chair_type') == 'manual_4_wheel' ? 'selected' : '' }}>
                                    {{ __('Manuale a 4 ruote') }}</option>
                                <option value="manual_tracks"
                                    {{ old('chair_type') == 'manual_tracks' ? 'selected' : '' }}>
                                    {{ __('Manuale cingolata') }}</option>
                            </select>
                            @error('chair_type')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="field">
                            <label for="max_weight_kg">{{ __('Kg massimo consentito') }}</label>
                            <input type="number" step="0.01" class="input @error('max_weight_kg') is-invalid @enderror"
                                id="max_weight_kg" name="max_weight_kg" value="{{ old('max_weight_kg') }}" min="0">
                            @error('max_weight_kg')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Sezione 2: Dettagli veicolo --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">2</span> {{ __('Dettagli veicolo') }}</h2>
                <div class="field">
                    <label for="vehicle_id">{{ __('Veicolo') }}</label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id">
                        <option value="">{{ __('Nessun veicolo associato') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                data-needs-oxygen-check="{{ $vehicle->vehicleType?->needs_oxygen_check ? '1' : '0' }}"
                                {{ old('vehicle_id', $selectedVehicleId) == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }} · {{ $vehicle->brand->name ?? 'N/A' }}
                                {{ $vehicle->carModel->name ?? 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="equipment-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva attrezzatura') }}
                </button>
                <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const typeSelect = document.getElementById('equipment_type_id');
                const extinguisherFields = document.getElementById('extinguisher-fields');
                const chairStretcherFields = document.getElementById('chair-stretcher-fields');
                const chairTypeField = document.getElementById('chair-type-field');

                const toggle = () => {
                    const selectedOption = typeSelect.options[typeSelect.selectedIndex];
                    const category = selectedOption ? selectedOption.dataset.category : null;

                    extinguisherFields.style.display = category === 'fire_extinguisher' ? '' : 'none';
                    chairStretcherFields.style.display = (category === 'chair' || category === 'stretcher') ? '' : 'none';
                    chairTypeField.style.display = category === 'chair' ? '' : 'none';
                };

                toggle();
                typeSelect.addEventListener('change', toggle);
            });
        </script>
    @endpush
@endsection
