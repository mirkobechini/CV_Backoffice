@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.mileage-logs.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica registro chilometri</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="mileage-log-form" method="POST" action="{{ route('admin.mileage-logs.update', $mileageLog->id) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli Registro Chilometri</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        data-needs-oxygen-check="{{ $vehicle->vehicleType?->needs_oxygen_check ? '1' : '0' }}"
                                        {{ old('vehicle_id', $mileageLog->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand }} {{ $vehicle->model }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="log_date" class="form-label">Data del registro</label>
                            <input type="date" class="form-control @error('log_date') is-invalid @enderror" id="log_date"
                                name="log_date" value="{{ old('log_date', $mileageLog->log_date) }}" required>
                            @error('log_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="mileage" class="form-label">Chilometraggio</label>
                            <input type="number" class="form-control @error('mileage') is-invalid @enderror" id="mileage"
                                name="mileage" value="{{ old('mileage', $mileageLog->mileage) }}" required>
                            @error('mileage')
                                <div class="invalid-feedback">{{ $message }}</div>
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
        document.getElementById('mileage-log-form').addEventListener('submit', function() {
            const submitButton = document.getElementById('issue-submit-btn');
            submitButton.disabled = true;
            submitButton.innerText = submitButton.getAttribute('data-loading-text');
        });
    </script>
@endsection
