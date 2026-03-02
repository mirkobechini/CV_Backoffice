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
                <form id="deadline-form" method="POST" action="{{ route('admin.deadlines.store') }}" enctype="multipart/form-data"
                    data-single-submit="true">
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
                                        {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
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
                                <option value="Assicurazione" {{ old('type') == 'Assicurazione' ? 'selected' : '' }}>Assicurazione</option>
                                <option value="Revisione Ministeriale" {{ old('type') == 'Revisione Ministeriale' ? 'selected' : '' }}>Revisione Ministeriale</option>
                                <option value="Revisione Impianto Ossigeno" {{ old('type') == 'Revisione Impianto Ossigeno' ? 'selected' : '' }}>Revisione Impianto Ossigeno</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Data di scadenza</label>
                            <input type="date" class="form-control @error('due_date') is-invalid @enderror"
                                id="due_date" name="due_date" value="{{ old('due_date') }}" required>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status"
                                required>
                                <option value="">Seleziona uno stato</option>
                                <option value="renewed" {{ old('status') == 'renewed' ? 'selected' : '' }}>Rinnovata</option>
                                <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>In scadenza</option>
                                <option value="expired" {{ old('status') == 'expired' ? 'selected' : '' }}>Scaduta</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="issue-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva</button>
                </form>
            </div>
        </div>
    </div>
@endsection
