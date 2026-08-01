@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.mileage-logs.index') }}" class="btn btn-secondary">Torna ai chilometraggi</a>
            </div>
        </div>
        <h1 class="mb-4">Rilevazione mensile chilometri</h1>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Si sono verificati degli errori:</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.mileage-logs.bulk-store') }}" data-single-submit="true">
                    @csrf
                    <div class="mb-3">
                        <label for="log_date" class="form-label">Data rilevazione</label>
                        <input type="date" class="form-control @error('log_date') is-invalid @enderror" id="log_date"
                            name="log_date" value="{{ old('log_date', date('Y-m-d')) }}" required>
                        @error('log_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Sigla</th>
                                    <th>Targa</th>
                                    <th>Marca / Modello</th>
                                    <th>Ultimo km</th>
                                    <th>Km attuali</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($vehicles as $vehicle)
                                    <tr>
                                        <td><strong>{{ $vehicle->internal_code }}</strong></td>
                                        <td>{{ $vehicle->license_plate }}</td>
                                        <td>{{ $vehicle->brand?->name ?? 'N/A' }} {{ $vehicle->carModel?->name ?? '' }}</td>
                                        <td>{{ $vehicle->mileage !== null ? number_format($vehicle->mileage, 0, ',', '.') : 'N/D' }}
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm"
                                                style="max-width: 180px;" name="mileages[{{ $vehicle->id }}]"
                                                value="{{ old('mileages.' . $vehicle->id) }}" min="0"
                                                placeholder="km">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">Nessun veicolo disponibile.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="text-muted">Inserisci solo i km dei mezzi rilevati. I campi vuoti verranno
                            ignorati.</span>
                        <button type="submit" class="btn btn-primary" data-loading-text="Salvataggio in corso...">
                            <i class="bi bi-save"></i> Salva chilometraggi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
