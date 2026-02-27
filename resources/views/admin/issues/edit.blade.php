@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1 class="mb-4">Modifica guasto</h1>
        <div class="card my-0">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.issues.update', $issue->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli Guasto</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        {{ old('vehicle_id', $issue->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand }} {{ $vehicle->model }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Data del guasto</label>
                            <input type="date" class="form-control @error('event_date') is-invalid @enderror"
                                id="event_date" name="event_date" value="{{ old('event_date', $issue->event_date) }}" required>
                            @error('event_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Stato</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status"
                                required>
                                <option value="">Seleziona uno stato</option>
                                <option value="open" {{ old('status', $issue->status) == 'open' ? 'selected' : '' }}>Aperto</option>
                                <option value="in_progress" {{ old('status', $issue->status) == 'in_progress' ? 'selected' : '' }}>In
                                    lavorazione</option>
                                <option value="closed" {{ old('status', $issue->status) == 'closed' ? 'selected' : '' }}>Risolto</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrizione</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror"
                                id="description" name="description" value="{{ old('description', $issue->description) }}" required>
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
                    <button type="submit" class="btn btn-primary">Salva modifiche</button>
                </form>
            </div>
        </div>
    </div>
@endsection
