@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A'), 'url' => route('admin.equipments.show', $equipment->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica attrezzatura') }}</h1>
        <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="equipment-edit-form" method="POST" action="{{ route('admin.equipments.update', $equipment->id) }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf
            @method('PUT')

            {{-- Sezione 1: Dettagli attrezzatura --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli attrezzatura') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $equipment->name) }}" required>
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="serial_number">{{ __('Numero di serie') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('serial_number') is-invalid @enderror"
                            id="serial_number" name="serial_number"
                            value="{{ old('serial_number', $equipment->serial_number) }}" required>
                        @error('serial_number')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="equipment_type_id">{{ __('Tipo di attrezzatura') }} <span class="req">*</span></label>
                    <select class="select @error('equipment_type_id') is-invalid @enderror" id="equipment_type_id"
                        name="equipment_type_id" required>
                        <option value="" disabled>{{ __('Seleziona un tipo di attrezzatura') }}</option>
                        @foreach ($equipmentTypes as $type)
                            <option value="{{ $type->id }}"
                                {{ old('equipment_type_id', $equipment->equipment_type_id) == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('equipment_type_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <x-form.date-input name="revision_date" label="{{ __('Data revisione') }}" :model="$equipment" />
                    <x-form.date-input name="expiration_date" label="{{ __('Data scadenza') }}" :model="$equipment" />
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
                                {{ old('vehicle_id', $equipment->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
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
                <button id="equipment-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
