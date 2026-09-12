@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tipi di veicoli'), 'url' => route('admin.vehicle-types.index')],
        ['label' => $vehicleType->name, 'url' => route('admin.vehicle-types.show', $vehicleType->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica tipo di veicolo') }}</h1>
        <a href="{{ request('back', route('admin.vehicle-types.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="vehicle-type-form" method="POST" action="{{ route('admin.vehicle-types.update', $vehicleType->id) }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf
            @method('PUT')

            {{-- Sezione 1: Dettagli tipo --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli tipo di veicolo') }}</h2>
                <div class="field">
                    <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name', $vehicleType->name) }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="first_inspection_months">{{ __('Dopo quanti mesi la prima revisione') }} <span
                                class="req">*</span></label>
                        <input type="number"
                            class="input @error('first_inspection_months') is-invalid @enderror"
                            id="first_inspection_months" name="first_inspection_months"
                            value="{{ old('first_inspection_months', $vehicleType->first_inspection_months) }}"
                            min="0" required>
                        @error('first_inspection_months')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="regular_inspection_months">{{ __('Dopo quanti mesi le successive revisioni') }}
                            <span class="req">*</span></label>
                        <input type="number"
                            class="input @error('regular_inspection_months') is-invalid @enderror"
                            id="regular_inspection_months" name="regular_inspection_months"
                            value="{{ old('regular_inspection_months', $vehicleType->regular_inspection_months) }}"
                            min="0" required>
                        @error('regular_inspection_months')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <input type="hidden" name="needs_oxygen_check" value="0">
                <label class="switch-field">
                    <input type="checkbox" class="switch-input" id="needs_oxygen_check" name="needs_oxygen_check"
                        value="1" {{ old('needs_oxygen_check', $vehicleType->needs_oxygen_check) ? 'checked' : '' }}>
                    <span class="track"><span class="knob"></span></span>
                    <span class="label">{{ __('Revisione ossigeno') }}</span>
                </label>
                @error('needs_oxygen_check')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            {{-- Sezione 2: Equipaggiamento necessario --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">2</span> {{ __('Equipaggiamento necessario') }}</h2>
                @php
                    $selectedEquipmentTypes = old(
                        'required_equipment_types',
                        $equipmentTypeRequirements->pluck('id')->all(),
                    );
                    $requiredEquipmentQty = old(
                        'required_equipment_types_qty',
                        $equipmentTypeRequirements->pluck('pivot.required_quantity')->all(),
                    );
                    $rowsCount = max(count($selectedEquipmentTypes), count($requiredEquipmentQty), 1);
                    $equipmentOptions = $equipmentTypes
                        ->map(fn($type) => ['id' => $type->id, 'name' => $type->name])
                        ->values();
                @endphp

                <div class="eq-rows">
                    <div id="equipment-rows" data-equipment-options='@json($equipmentOptions)'>
                        @for ($i = 0; $i < $rowsCount; $i++)
                            <div class="equipment-row eq-row">
                                <select
                                    class="select {{ $errors->has('required_equipment_types') || $errors->has('required_equipment_types.*') ? 'is-invalid' : '' }}"
                                    id="required_equipment_types_{{ $i }}" name="required_equipment_types[]">
                                    <option value="" disabled
                                        {{ ($selectedEquipmentTypes[$i] ?? '') === '' ? 'selected' : '' }}>
                                        {{ __('Seleziona equipaggiamento') }}</option>
                                    @foreach ($equipmentTypes as $type)
                                        <option value="{{ $type->id }}"
                                            {{ (string) ($selectedEquipmentTypes[$i] ?? '') === (string) $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number"
                                    class="input qty {{ $errors->has('required_equipment_types_qty') || $errors->has('required_equipment_types_qty.*') ? 'is-invalid' : '' }}"
                                    name="required_equipment_types_qty[]" value="{{ $requiredEquipmentQty[$i] ?? 1 }}"
                                    min="0">
                                <button type="button" class="del remove-equipment-btn" title="{{ __('Rimuovi') }}"><i
                                        class="fa-solid fa-xmark"></i></button>
                            </div>
                        @endfor
                    </div>

                    <button type="button" class="eq-add" id="add-equipment-btn">
                        <i class="fa-solid fa-plus"></i> {{ __('Aggiungi attrezzatura') }}
                    </button>
                </div>

                @if ($errors->has('required_equipment_types') || $errors->has('required_equipment_types.*'))
                    <div class="field-error">
                        {{ $errors->first('required_equipment_types') ?: $errors->first('required_equipment_types.*') }}
                    </div>
                @endif
                @if ($errors->has('required_equipment_types_qty') || $errors->has('required_equipment_types_qty.*'))
                    <div class="field-error">
                        {{ $errors->first('required_equipment_types_qty') ?: $errors->first('required_equipment_types_qty.*') }}
                    </div>
                @endif
            </div>

            <div class="form-actions">
                <button id="vehicle-type-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.vehicle-types.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
