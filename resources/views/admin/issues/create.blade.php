@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.issues.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Aggiungi nuovo guasto</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="issue-form" method="POST" action="{{ route('admin.issues.store') }}" enctype="multipart/form-data"
                    data-single-submit="true">
                    @csrf
                    <section class="mb-3 row">
                        <h2>Dettagli Guasto</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        {{ old('vehicle_id', $selectedVehicleId) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand->name ?? 'N/A' }} {{ $vehicle->carModel->name ?? 'N/A' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <x-form.date-input name="event_date" label="Data del guasto" required />
                        <div class="mb-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status"
                                required>
                                <option value="">Seleziona uno stato</option>
                                <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>Aperto</option>
                                <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>In
                                    lavorazione</option>
                                <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>Risolto</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror"
                                id="description" name="description" value="{{ old('description') }}" required>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Immagine (opzionale)</label>
                            <input type="file" class="form-control @error('image') is-invalid @enderror" id="image"
                                name="image" accept="image/*">
                            @error('image')
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
