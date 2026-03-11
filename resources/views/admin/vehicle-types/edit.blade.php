@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.vehicle-types.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica tipo di veicolo</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="vehicle-type-form" method="POST"
                    action="{{ route('admin.vehicle-types.update', $vehicleType->id) }}" enctype="multipart/form-data"
                    data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli Tipo di Veicolo</h2>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name', $vehicleType->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="extinguishers_required" class="form-label">Numero di estintori necessari</label>
                            <input type="text" class="form-control @error('extinguishers_required') is-invalid @enderror"
                                id="extinguishers_required" name="extinguishers_required"
                                value="{{ old('extinguishers_required', $vehicleType->extinguishers_required) }}" required>
                            @error('extinguishers_required')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="first_inspection_months" class="form-label">Dopo quanti mesi la prima
                                revisione</label>
                            <input type="number"
                                class="form-control @error('first_inspection_months') is-invalid @enderror"
                                id="first_inspection_months" name="first_inspection_months"
                                value="{{ old('first_inspection_months', $vehicleType->first_inspection_months) }}"
                                required>
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
                                value="{{ old('regular_inspection_months', $vehicleType->regular_inspection_months) }}"
                                required>
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
                                    {{ old('needs_oxygen_check', $vehicleType->needs_oxygen_check) ? 'checked' : '' }}>
                                <label for="needs_oxygen_check" class="form-check-label">Revisione ossigeno</label>
                                @error('needs_oxygen_check')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </section>
                    <button id="issue-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva modifiche</button>
                </form>
            </div>
        </div>
    </div>
@endsection
