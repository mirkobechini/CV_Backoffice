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
                                        {{ $vehicle->internal_code }} - {{ $vehicle->brand->name ?? 'N/A' }}
                                        {{ $vehicle->carModel->name ?? 'N/A' }}
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
                        <div class="mb-3 position-relative">
                            <label for="description" class="form-label">Descrizione</label>
                            <input type="text" class="form-control @error('description') is-invalid @enderror"
                                id="description" name="description" value="{{ old('description') }}" required
                                autocomplete="off" data-autocomplete="{{ route('api.issues.suggestions') }}">
                            <div id="autocomplete-results" class="list-group position-absolute w-100 shadow"
                                style="z-index:1000; display:none; max-height:250px; overflow-y:auto;"></div>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Note (opzionale)</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea>
                            @error('notes')
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

@push('scripts')
    <script>
        (function() {
            const input = document.getElementById('description');
            const results = document.getElementById('autocomplete-results');
            let timer;

            input.addEventListener('input', function() {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 2) {
                    results.style.display = 'none';
                    return;
                }
                timer = setTimeout(() => {
                    fetch(input.dataset.autocomplete + '?q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            if (!data.length) {
                                results.style.display = 'none';
                                return;
                            }
                            results.innerHTML = data.map(item =>
                                `<button type="button" class="list-group-item list-group-item-action" onclick="selectDescription('${item.description.replace(/'/g, "\\'")}', this)">
                            ${item.description}
                            <small class="text-muted ms-2">(${item.total}x)</small>
                        </button>`
                            ).join('');
                            results.style.display = 'block';
                        });
                }, 300);
            });

            input.addEventListener('blur', () => setTimeout(() => results.style.display = 'none', 200));
            input.addEventListener('focus', () => {
                if (results.children.length) results.style.display = 'block';
            });

            window.selectDescription = function(val, el) {
                input.value = val;
                results.style.display = 'none';
            };
        })();
    </script>
@endpush
