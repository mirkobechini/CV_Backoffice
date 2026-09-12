@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Guasti'), 'url' => route('admin.issues.index')],
        ['label' => $issue->description, 'url' => route('admin.issues.show', $issue->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica guasto') }}</h1>
        <a href="{{ request('back', route('admin.issues.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="issue-edit-form" method="POST" action="{{ route('admin.issues.update', $issue->id) }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli guasto') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                        <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                            name="vehicle_id" required>
                            <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                    {{ old('vehicle_id', $issue->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->internal_code }} · {{ $vehicle->brand->name ?? 'N/A' }}
                                    {{ $vehicle->carModel->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <x-form.date-input name="event_date" label="{{ __('Data del guasto') }}" :model="$issue" required />
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                        <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="" disabled selected>{{ __('Seleziona uno stato') }}</option>
                            <option value="open" {{ old('status', $issue->status) == 'open' ? 'selected' : '' }}>
                                {{ __('Aperto') }}</option>
                            <option value="in_progress"
                                {{ old('status', $issue->status) == 'in_progress' ? 'selected' : '' }}>
                                {{ __('In lavorazione') }}</option>
                            <option value="closed" {{ old('status', $issue->status) == 'closed' ? 'selected' : '' }}>
                                {{ __('Risolto') }}</option>
                        </select>
                        @error('status')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field" style="position:relative;">
                        <label for="description">{{ __('Descrizione') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('description') is-invalid @enderror" id="description"
                            name="description" value="{{ old('description', $issue->description) }}" required
                            autocomplete="off" data-autocomplete="{{ route('api.issues.suggestions') }}">
                        <div id="autocomplete-results" class="autocomplete-results" style="display:none;"></div>
                        @error('description')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note (opzionale)') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $issue->notes) }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="image">{{ __('Immagine (opzionale)') }}</label>
                    <label class="file-drop" for="image" id="image_label">
                        @if ($issue->photo)
                            <i class="fa-solid fa-file-circle-check"></i> {{ __('File caricato · clicca per sostituire') }}
                        @else
                            <i class="fa-solid fa-image"></i> {{ __('Clicca per caricare una foto') }}
                        @endif
                    </label>
                    <input type="file" class="@error('image') is-invalid @enderror" id="image" name="image"
                        accept="image/*" hidden>
                    @error('image')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    @if ($issue->photo)
                        <div class="hint"><a href="{{ asset('storage/' . $issue->photo) }}" target="_blank"
                                rel="noopener noreferrer">{{ __('Apri immagine attuale') }}</a></div>
                    @endif
                </div>
            </div>

            <div class="form-actions">
                <button id="issue-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.issues.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageInput = document.getElementById('image');
            const imageLabel = document.getElementById('image_label');
            const imageDefaultText = imageLabel.innerHTML;

            imageInput.addEventListener('change', function() {
                if (imageInput.files.length > 0) {
                    imageLabel.textContent = imageInput.files[0].name;
                } else {
                    imageLabel.innerHTML = imageDefaultText;
                }
            });
        });

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
                                `<button type="button" class="autocomplete-item" onclick="selectDescription('${item.description.replace(/'/g, "\\'")}', this)">
                            ${item.description}
                            <small>(${item.total}x)</small>
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
