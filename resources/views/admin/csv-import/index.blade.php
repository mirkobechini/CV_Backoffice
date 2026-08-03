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
                            Massimo 5MB.
                        </div>
                    </div>

                    <div class="mb-3" id="vehicle-ref-group" style="display:none;">
                        <label for="vehicle_ref" class="form-label">Sigla veicolo (per guasti)</label>
                        <input type="text" class="form-control" id="vehicle_ref" name="vehicle_ref"
                            placeholder="es. 1727">
                        <div class="form-text">
                            Se il file CSV non contiene la colonna "veicolo", inserisci qui la sigla (es. 1727).
                            Se il nome file contiene la sigla (es. "1727 - Guasti.csv"), verrà rilevata
                            automaticamente.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Anteprima e validazione</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Formati file CSV supportati</h5>
            </div>
            <div class="card-body">
                <h6>Guasti (dal tuo foglio Google)</h6>
                <pre class="bg-light p-2 rounded"><code>DATA;DESCRIZIONE;...;APPUNTAMENTO;OFFICINA;RISOLTO
17/07/2024;Pasticche Freni;;;17/07/2024;Mezzani;17/07/2024</code></pre>
                <p class="text-muted small mb-0">
                    Il veicolo va specificato nel nome file (es. "1727 - Guasti.csv") o nel campo apposito.
                    La colonna RISOLTO con "ok" o "x" imposta il guasto come chiuso.
                    Le date supportano formati GG/MM/AAAA, GG/MM e GG/MM/AA.
                </p>

                <hr>

                <h6>Chilometraggi (formato pivot — dal tuo foglio Google)</h6>
                <pre class="bg-light p-2 rounded"><code>SIGLA;MEZZI;TARGA;GENNAIO;FEBBRAIO;MARZO;...
1726;DUCATO;GP 365 YL;30742;32556;35907;...</code></pre>
                <p class="text-muted small mb-0">
                    Ogni riga è un veicolo, le colonne sono i mesi (italiano). I km vengono
                    registrati al 1° del mese. I veicoli vengono riconosciuti per sigla o targa.
                    I campi vuoti vengono ignorati.
                </p>

                <hr>

                <h6>Chilometraggi (formato semplice alternativo)</h6>
                <pre class="bg-light p-2 rounded"><code>veicolo;mese;chilometri
AB123CD;03/2026;45000
1001;03/2026;32000</code></pre>
                <p class="text-muted small mb-0">Formato alternativo: una riga per lettura.</p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('entity').addEventListener('change', function() {
            const group = document.getElementById('vehicle-ref-group');
            group.style.display = this.value === 'issues' ? 'block' : 'none';
        });

        // Auto-detect sigla dal nome file
        document.getElementById('csv_file').addEventListener('change', function() {
            const filename = this.files[0]?.name || '';
            const match = filename.match(/^(\d+)\s*[-–—]/);
            if (match) {
                document.getElementById('vehicle_ref').value = match[1];
            }
        });
    </script>
@endsection
