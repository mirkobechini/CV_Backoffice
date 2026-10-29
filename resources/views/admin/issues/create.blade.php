@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Manutenzione')],
        ['label' => __('Guasti'), 'url' => route('admin.issues.index')],
        ['label' => __('Nuovo guasto')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo guasto') }}</h1>
        <a href="{{ request('back', route('admin.issues.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="issue-form" method="POST" action="{{ route('admin.issues.store') }}" enctype="multipart/form-data"
            data-single-submit="true">
            @csrf

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
                                    {{ old('vehicle_id', $selectedVehicleId) == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->internal_code }} · {{ $vehicle->brand->name ?? 'N/A' }}
                                    {{ $vehicle->carModel->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <x-form.date-input name="event_date" label="{{ __('Data del guasto') }}" required />
                </div>

                <div id="open-issues-panel" class="alert warning" style="display:none; margin-bottom:14px;">
                    <strong>{{ __('Guasti già aperti per questo veicolo') }}</strong>
                    <div id="open-issues-list" style="margin-top:8px; display:flex; flex-direction:column; gap:6px;">
                    </div>
                    @foreach ($openIssues as $issue)
                        <a href="{{ route('admin.issues.show', $issue->id) }}" class="open-issue-item" target="_blank"
                            data-vehicle-id="{{ $issue->vehicle_id }}" data-part="{{ $issue->part }}"
                            data-description="{{ $issue->description }}"
                            data-status="{{ $issue->status === 'open' ? __('Aperto') : __('In lavorazione') }}"
                            data-date="{{ $issue->event_date?->format('d/m/Y') }}" hidden></a>
                    @endforeach
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="part">{{ __('Parte del veicolo coinvolta') }} <span class="req">*</span></label>
                        <select class="select @error('part') is-invalid @enderror" id="part" name="part" required>
                            <option value="" disabled {{ old('part') ? '' : 'selected' }}>{{ __('Seleziona...') }}</option>
                            @foreach (\App\Models\Issue::sortedParts() as $part)
                                <option value="{{ $part }}" {{ old('part') == $part ? 'selected' : '' }}>{{ $part }}</option>
                            @endforeach
                        </select>
                        @error('part')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                @php($issueTireIds = old('tire_ids', []))
                <label class="check" style="margin-bottom:8px;">
                    <input type="checkbox" id="is-tire-issue" {{ !empty($issueTireIds) ? 'checked' : '' }}>
                    <div>
                        <div class="label">{{ __('Guasto relativo a uno o più pneumatici') }}</div>
                        <div class="sub">{{ __('Es. foratura, usura anomala') }}</div>
                    </div>
                </label>
                <div class="field" id="tire-id-field" style="{{ !empty($issueTireIds) ? '' : 'display:none;' }}">
                    <label>{{ __('Pneumatici collegati') }}</label>
                    <div class="check-list" id="tire-checkboxes">
                        @error('tire_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @foreach ($tires as $tire)
                            <div class="item tire-checkbox" data-vehicle-id="{{ $tire->vehicle_id }}"
                                style="display:none;">
                                <input type="checkbox" name="tire_ids[]" value="{{ $tire->id }}"
                                    id="tire_{{ $tire->id }}"
                                    {{ in_array((string) $tire->id, $issueTireIds) ? 'checked' : '' }}>
                                <label class="lbl" for="tire_{{ $tire->id }}">
                                    {{ $tire->position_label }} · {{ $tire->season_label }}
                                    @if ($tire->brand)
                                        <span class="meta">— {{ $tire->brand }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                        <div class="empty" id="no-tire-msg" style="display:none;">
                            {{ __('Nessun pneumatico per il veicolo selezionato.') }}
                        </div>
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                        <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="" disabled selected>{{ __('Seleziona uno stato') }}</option>
                            <option value="open" {{ old('status') == 'open' ? 'selected' : '' }}>{{ __('Aperto') }}
                            </option>
                            <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>
                                {{ __('In lavorazione') }}</option>
                            <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>
                                {{ __('Risolto') }}</option>
                        </select>
                        @error('status')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field" style="position:relative;">
                        <label for="description">{{ __('Descrizione') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('description') is-invalid @enderror" id="description"
                            name="description" value="{{ old('description') }}" required autocomplete="off"
                            data-autocomplete="{{ route('api.issues.suggestions') }}">
                        <div id="autocomplete-results" class="autocomplete-results" style="display:none;"></div>
                        @error('description')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note (opzionale)') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="image">{{ __('Immagine (opzionale)') }}</label>
                    <label class="file-drop" for="image" id="image_label">
                        <i class="fa-solid fa-image"></i> {{ __('Clicca per caricare una foto') }}
                    </label>
                    <input type="file" class="heic-convert @error('image') is-invalid @enderror" id="image"
                        name="image" accept="image/*,.heic,.heif" hidden>
                    @error('image')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="issue-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva') }}
                </button>
                <a href="{{ request('back', route('admin.issues.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isTireIssue = document.getElementById('is-tire-issue');
            const tireIdField = document.getElementById('tire-id-field');
            const vehicleSelect = document.getElementById('vehicle_id');
            const noTireMsg = document.getElementById('no-tire-msg');

            const toggleTireField = () => {
                tireIdField.style.display = isTireIssue.checked ? '' : 'none';
                if (!isTireIssue.checked) {
                    document.querySelectorAll('.tire-checkbox input').forEach(input => input.checked = false);
                }
            };

            // Mostra solo le gomme del veicolo selezionato: senza questo
            // filtro l'elenco mescolava le gomme di tutti i veicoli del
            // gruppo, rendendo facile collegare per errore la gomma di un
            // altro mezzo.
            const filterTiresByVehicle = () => {
                const selectedVehicleId = vehicleSelect.value;
                const tireChecks = document.querySelectorAll('.tire-checkbox');
                let hasVisibleTire = false;

                tireChecks.forEach(el => {
                    const matches = el.dataset.vehicleId === selectedVehicleId;
                    el.style.display = matches ? '' : 'none';
                    if (matches) {
                        hasVisibleTire = true;
                    } else {
                        el.querySelector('input').checked = false;
                    }
                });

                noTireMsg.style.display = (selectedVehicleId && !hasVisibleTire) ? '' : 'none';
            };

            // Mostra i guasti già aperti del veicolo selezionato, per far
            // vedere a chi sta segnalando un nuovo guasto se il problema è
            // già noto, prima che lo scriva da capo con parole diverse.
            const openIssuesPanel = document.getElementById('open-issues-panel');
            const openIssuesList = document.getElementById('open-issues-list');
            const openIssueItems = document.querySelectorAll('.open-issue-item');

            const filterOpenIssuesByVehicle = () => {
                const selectedVehicleId = vehicleSelect.value;
                const matches = Array.from(openIssueItems).filter(el => el.dataset.vehicleId === selectedVehicleId);

                openIssuesList.innerHTML = '';
                matches.forEach(el => {
                    const link = document.createElement('a');
                    link.href = el.getAttribute('href');
                    link.target = '_blank';
                    link.className = 'dash-list-item';
                    link.style.cssText = 'background:var(--surface-2);border-radius:8px;padding:8px 10px;';

                    const info = document.createElement('div');
                    const name = document.createElement('div');
                    name.className = 'name';
                    name.textContent = el.dataset.part;
                    const meta = document.createElement('div');
                    meta.className = 'meta';
                    meta.textContent = `${el.dataset.description} — ${el.dataset.date}`;
                    info.append(name, meta);

                    const badge = document.createElement('span');
                    badge.className = 'badge b-amber';
                    badge.textContent = el.dataset.status;

                    link.append(info, badge);
                    openIssuesList.append(link);
                });

                openIssuesPanel.style.display = matches.length ? '' : 'none';
            };

            isTireIssue.addEventListener('change', toggleTireField);
            vehicleSelect.addEventListener('change', filterTiresByVehicle);
            vehicleSelect.addEventListener('change', filterOpenIssuesByVehicle);
            filterOpenIssuesByVehicle();
            filterTiresByVehicle();
        });

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
                            // Costruzione DOM diretta invece di innerHTML/onclick con
                            // stringa interpolata (falla XSS, audit 2026-10-22, SH5):
                            // item.description viene da altri utenti, mai eseguita
                            // come HTML.
                            results.replaceChildren(...data.map(item => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'autocomplete-item';
                                btn.append(document.createTextNode(item.description + ' '));
                                const small = document.createElement('small');
                                small.textContent = `(${item.total}x)`;
                                btn.appendChild(small);
                                btn.addEventListener('click', () => selectDescription(item.description));
                                return btn;
                            }));
                            results.style.display = 'block';
                        });
                }, 300);
            });

            input.addEventListener('blur', () => setTimeout(() => results.style.display = 'none', 200));
            input.addEventListener('focus', () => {
                if (results.children.length) results.style.display = 'block';
            });

            window.selectDescription = function(val) {
                input.value = val;
                results.style.display = 'none';
            };
        })();
    </script>
@endpush
