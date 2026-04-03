@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.vehicle-types.index')) }}" class="btn btn-secondary">Torna alla
                    pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Aggiungi nuovo tipo di veicolo</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="vehicle-type-form" method="POST" action="{{ route('admin.vehicle-types.store') }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    <section class="mb-3 row">
                        <h2>Dettagli Tipo di Veicolo</h2>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="first_inspection_months" class="form-label">Dopo quanti mesi la prima
                                revisione</label>
                            <input type="number"
                                class="form-control @error('first_inspection_months') is-invalid @enderror"
                                id="first_inspection_months" name="first_inspection_months"
                                value="{{ old('first_inspection_months') }}" required>
                            @error('first_inspection_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="regular_inspection_months" class="form-label">Dopo quanti mesi le successive
                                revisioni</label>
                            <input type="number"
                                class="form-control @error('regular_inspection_months') is-invalid @enderror"
                                id="regular_inspection_months" name="regular_inspection_months"
                                value="{{ old('regular_inspection_months') }}" required>
                            @error('regular_inspection_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <input type="hidden" name="needs_oxygen_check" value="0">
                            <div class="form-check form-switch">
                                <input type="checkbox"
                                    class="form-check-input @error('needs_oxygen_check') is-invalid @enderror"
                                    id="needs_oxygen_check" name="needs_oxygen_check" value="1"
                                    {{ old('needs_oxygen_check') ? 'checked' : '' }}>
                                <label for="needs_oxygen_check" class="form-check-label">Revisione ossigeno</label>
                                @error('needs_oxygen_check')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <fieldset class="mb-3">
                            <legend class="mb-3">Equipaggiamento necessario</legend>
                            @php
                                $selectedEquipmentTypes = old('required_equipment_types', ['']);
                                $requiredEquipmentQty = old('required_equipment_types_qty', [1]);
                                $rowsCount = max(count($selectedEquipmentTypes), count($requiredEquipmentQty), 1);
                                $equipmentOptions = $equipmentTypes
                                    ->map(function ($type) {
                                        return [
                                            'id' => $type->id,
                                            'name' => $type->name,
                                        ];
                                    })
                                    ->values();
                            @endphp

                            <div id="equipment-rows" data-equipment-options='@json($equipmentOptions)'>
                                @for ($i = 0; $i < $rowsCount; $i++)
                                    <div class="equipment-row d-flex gap-2 mb-2">
                                        <select
                                            class="form-select {{ $errors->has('required_equipment_types') || $errors->has('required_equipment_types.*') ? 'is-invalid' : '' }}"
                                            id="required_equipment_types_{{ $i }}"
                                            name="required_equipment_types[]">
                                            <option value="" disabled
                                                {{ ($selectedEquipmentTypes[$i] ?? '') === '' ? 'selected' : '' }}>
                                                Seleziona equipaggiamento</option>
                                            @foreach ($equipmentTypes as $type)
                                                <option value="{{ $type->id }}"
                                                    {{ (string) ($selectedEquipmentTypes[$i] ?? '') === (string) $type->id ? 'selected' : '' }}>
                                                    {{ $type->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="number"
                                            class="form-control {{ $errors->has('required_equipment_types_qty') || $errors->has('required_equipment_types_qty.*') ? 'is-invalid' : '' }}"
                                            name="required_equipment_types_qty[]"
                                            value="{{ $requiredEquipmentQty[$i] ?? 1 }}" min="0">
                                        <button type="button"
                                            class="btn btn-outline-danger remove-equipment-btn">Rimuovi</button>
                                    </div>
                                @endfor
                            </div>

                            @if ($errors->has('required_equipment_types') || $errors->has('required_equipment_types.*'))
                                <div class="invalid-feedback d-block">
                                    {{ $errors->first('required_equipment_types') ?: $errors->first('required_equipment_types.*') }}
                                </div>
                            @endif
                            @if ($errors->has('required_equipment_types_qty') || $errors->has('required_equipment_types_qty.*'))
                                <div class="invalid-feedback d-block">
                                    {{ $errors->first('required_equipment_types_qty') ?: $errors->first('required_equipment_types_qty.*') }}
                                </div>
                            @endif

                            <button type="button" class="btn btn-link mt-2 px-0" id="add-equipment-btn">+ Aggiungi altro
                                equipaggiamento</button>
                        </fieldset>
                    </section>
                    <button id="issue-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva</button>
                </form>
            </div>
        </div>
    </div>
@endsection
