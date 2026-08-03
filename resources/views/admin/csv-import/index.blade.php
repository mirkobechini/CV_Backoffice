@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ url()->previous() }}" class="btn btn-secondary">Torna indietro</a>
            </div>
        </div>

        <h1 class="mb-4">Importa dati da CSV</h1>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('status_error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>{{ session('status_error') }}</strong>
                @if (session('status_errors'))
                    <ul class="mb-0 mt-1">
                        @foreach (session('status_errors') as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form action="{{ route('admin.csv-import.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="entity" class="form-label">Tipo dati</label>
                        <select class="form-select" id="entity" name="entity" required>
                            <option value="">Seleziona...</option>
                            <option value="issues" {{ old('entity') == 'issues' ? 'selected' : '' }}>Guasti</option>
                            <option value="mileage-logs" {{ old('entity') == 'mileage-logs' ? 'selected' : '' }}>
                                Chilometraggi</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="csv_file" class="form-label">File CSV</label>
                        <input type="file" class="form-control @error('csv_file') is-invalid @enderror" id="csv_file"
                            name="csv_file" accept=".csv,.txt" required>
                        @error('csv_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Il file deve avere la prima riga con gli header (es. "descrizione;veicolo;stato;data").
                            Massimo 2MB.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Anteprima e validazione</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Formato file CSV</h5>
            </div>
            <div class="card-body">
                <h6>Guasti</h6>
                <pre class="bg-light p-2 rounded"><code>descrizione;veicolo;stato;data
Faro rotto;AB123CD;aperto;01/03/2026
Frizione da sostituire;1001;open;15/03/2026</code></pre>
                <p class="text-muted small mb-0">Il veicolo può essere targa o sigla. Stato opzionale (default: aperto).
                    Data opzionale (default: oggi).</p>

                <hr>

                <h6>Chilometraggi</h6>
                <pre class="bg-light p-2 rounded"><code>veicolo;mese;chilometri
AB123CD;03/2026;45000
1001;03/2026;32000</code></pre>
                <p class="text-muted small mb-0">Il mese va inserito come MM/AAAA. I km vengono registrati al 1° del mese.
                </p>
            </div>
        </div>
    </div>
@endsection
