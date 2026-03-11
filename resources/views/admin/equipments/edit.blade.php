@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica attrezzatura</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="equipment-form" method="POST" action="{{ route('admin.equipments.update', $equipment->id) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli Attrezzatura</h2>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name', $equipment->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="serial_number" class="form-label">Numero di serie</label>
                            <input type="text" class="form-control @error('serial_number') is-invalid @enderror"
                                id="serial_number" name="serial_number"
                                value="{{ old('serial_number', $equipment->serial_number) }}" required>
                            @error('serial_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="equipment_type_id" class="form-label">Tipo di attrezzatura</label>
                            <select class="form-select @error('equipment_type_id') is-invalid @enderror"
                                id="equipment_type_id" name="equipment_type_id" required>
                                <option value="">Seleziona un tipo di attrezzatura</option>
                                @foreach ($equipmentTypes as $type)
                                    <option value="{{ $type->id }}"
                                        {{ old('equipment_type_id', $equipment->equipment_type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('equipment_type_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="revision_date" class="form-label">Data revisione</label>
                            <input type="date" class="form-control @error('revision_date') is-invalid @enderror"
                                id="revision_date" name="revision_date"
                                value="{{ old('revision_date', optional($equipment->revision_date)->format('Y-m-d')) }}">
                            @error('revision_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="expiration_date" class="form-label">Data scadenza</label>
                            <input type="date" class="form-control @error('expiration_date') is-invalid @enderror"
                                id="expiration_date" name="expiration_date"
                                value="{{ old('expiration_date', optional($equipment->expiration_date)->format('Y-m-d')) }}">
                            @error('expiration_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <section class="mb-3 row">
                        <h2>Dettagli Veicolo</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id">
                                <option value="">Nessun veicolo associato</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        data-needs-oxygen-check="{{ $vehicle->vehicleType?->needs_oxygen_check ? '1' : '0' }}"
                                        {{ old('vehicle_id', $equipment->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand }} {{ $vehicle->model }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="equipment-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('equipment-form').addEventListener('submit', function() {
            const submitButton = document.getElementById('equipment-submit-btn');
            submitButton.disabled = true;
            submitButton.innerText = submitButton.getAttribute('data-loading-text');
        });
    </script>
@endsection
