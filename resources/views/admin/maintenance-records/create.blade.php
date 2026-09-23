@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti'), 'url' => route('admin.maintenance-records.index')],
        ['label' => __('Nuovo appuntamento')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo appuntamento') }}</h1>
        <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="maintenance-record-form" method="POST" action="{{ route('admin.maintenance-records.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Dettagli veicolo --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli veicolo') }}</h2>
                <div class="field">
                    <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id"
                        required>
                        <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                {{ (string) old('vehicle_id', $preselectedVehicleId ?? '') === (string) $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field" id="issue-section" style="display:none;">
                    <label>{{ __('Guasti collegati') }}</label>
                    <div class="check-list" id="issue-checkboxes">
                        @error('issue_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @foreach ($openIssues as $issue)
                            <div class="item issue-checkbox" data-vehicle-id="{{ $issue->vehicle_id }}"
                                style="display:none;">
                                <input type="checkbox" name="issue_ids[]" value="{{ $issue->id }}"
                                    id="issue_{{ $issue->id }}"
                                    {{ in_array((string) $issue->id, old('issue_ids', $preselectedIssueId ? [$preselectedIssueId] : [])) ? 'checked' : '' }}>
                                <label class="lbl" for="issue_{{ $issue->id }}">
                                    {{ $issue->description }}
                                    @if ($issue->event_date)
                                        <span class="meta">— {{ $issue->event_date->format('d/m/Y') }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                        <div class="empty" id="no-issue-msg" style="display:none;">
                            {{ __('Nessun guasto aperto per il veicolo selezionato.') }}
                        </div>
                    </div>
                </div>

                @if ($closedIssues->isNotEmpty())
                    <div class="field" id="closed-issue-section" style="display:none;">
                        <label>{{ __('Guasti risolti (per registrare riparazioni avvenute)') }}</label>
                        <div class="check-list" id="closed-issue-checkboxes">
                            @foreach ($closedIssues as $issue)
                                <div class="item closed-issue-checkbox" data-vehicle-id="{{ $issue->vehicle_id }}"
                                    style="display:none;">
                                    <input type="checkbox" name="issue_ids[]" value="{{ $issue->id }}"
                                        id="closed_issue_{{ $issue->id }}">
                                    <label class="lbl" for="closed_issue_{{ $issue->id }}">
                                        {{ $issue->description }}
                                        @if ($issue->event_date)
                                            <span class="meta">— {{ $issue->event_date->format('d/m/Y') }}</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                            <div class="empty" id="no-closed-issue-msg" style="display:none;">
                                {{ __('Nessun guasto risolto per il veicolo selezionato.') }}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="field" id="deadline-section" style="display:none;">
                    <label>{{ __('Scadenze collegate') }}</label>
                    <div class="check-list" id="deadline-checkboxes">
                        @error('deadline_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @foreach ($pendingDeadlines as $deadline)
                            <div class="item deadline-checkbox" data-vehicle-id="{{ $deadline->vehicle_id }}"
                                style="display:none;">
                                <input type="checkbox" name="deadline_ids[]" value="{{ $deadline->id }}"
                                    id="deadline_{{ $deadline->id }}"
                                    {{ in_array((string) $deadline->id, old('deadline_ids', [])) ? 'checked' : '' }}>
                                <label class="lbl" for="deadline_{{ $deadline->id }}">
                                    {{ ucfirst($deadline->type) }} —
                                    {{ $deadline->due_date?->format('d/m/Y') ?? 'N/A' }}
                                    <span class="meta">({{ $deadline->days_label }})</span>
                                    <span
                                        class="badge {{ match ($deadline->status_color) {
                                            'red' => 'b-red',
                                            'yellow' => 'b-amber',
                                            'green' => 'b-green',
                                            default => 'b-gray',
                                        } }}">{{ $deadline->status_label }}</span>
                                </label>
                            </div>
                        @endforeach
                        <div class="empty" id="no-deadline-msg" style="display:none;">
                            {{ __('Nessuna scadenza in sospeso per il veicolo selezionato.') }}
                        </div>
                    </div>
                </div>

                <div class="field" id="no-issue-cta" style="display:none;">
                    <div class="check-list" style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                        <span class="hint" style="margin:0;">{{ __('Nessun guasto aperto per il veicolo selezionato.') }}</span>
                        <a id="create-issue-link" class="btn primary"
                            href="{{ route('admin.issues.create', ['back' => url()->full()]) }}">
                            {{ __('Crea guasto') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Sezione 2: Dettagli officina --}}
            <div class="form-section">
                <h2><span class="num">2</span> {{ __('Dettagli officina') }}</h2>
                <div class="field">
                    <label for="provider_id">{{ __('Officina') }} <span class="req">*</span></label>
                    <select class="select @error('provider_id') is-invalid @enderror" id="provider_id"
                        name="provider_id" required>
                        <option value="" disabled selected>{{ __("Seleziona un'officina") }}</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}"
                                {{ old('provider_id') == $provider->id ? 'selected' : '' }}>
                                {{ $provider->name }}</option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Sezione 3: Dettagli appuntamento --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">3</span> {{ __('Dettagli appuntamento') }}</h2>
                <div class="row2">
                    <x-form.date-input name="appointment_date" label="{{ __('Data appuntamento') }}" required />
                    <div class="field">
                        <label for="activity_type">{{ __('Tipo attività') }}</label>
                        <select class="select @error('activity_type') is-invalid @enderror" id="activity_type"
                            name="activity_type">
                            <option value="" disabled {{ old('activity_type', $preselectedActivityType) ? '' : 'selected' }}>{{ __('Seleziona...') }}</option>
                            @foreach (\App\Models\MaintenanceRecord::ACTIVITY_TYPES as $item)
                                <option value="{{ $item }}" {{ old('activity_type', $preselectedActivityType) == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div id="tire-section" style="display:none;">
                    <div class="field">
                        <label>{{ __('Set di gomme in magazzino da montare') }}</label>
                        <div class="check-list" id="tire-checkboxes">
                            @error('target_tire_ids')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                            @foreach ($storedTires as $tire)
                                <div class="item tire-checkbox" data-vehicle-id="{{ $tire->vehicle_id }}"
                                    style="display:none;">
                                    <input type="checkbox" name="target_tire_ids[]" value="{{ $tire->id }}"
                                        id="tire_{{ $tire->id }}"
                                        {{ in_array((string) $tire->id, old('target_tire_ids', [])) ? 'checked' : '' }}>
                                    <label class="lbl" for="tire_{{ $tire->id }}">
                                        {{ $tire->season_label }} ({{ $tire->axle_label }})
                                        @if ($tire->brand)
                                            <span class="meta">— {{ $tire->brand }}</span>
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                            <div class="empty" id="no-tire-msg" style="display:none;">
                                {{ __('Nessun set in magazzino per il veicolo selezionato.') }}
                            </div>
                        </div>
                        <div class="hint">{{ __('Seleziona uno o più set da montare insieme (es. anteriori + posteriori): devono coprire esattamente 4 gomme della stessa stagionalità. Puoi anche descrivere un set nuovo qui sotto, da solo o in aggiunta a quelli selezionati.') }}</div>
                    </div>
                    <div id="new-tire-fields">
                        <div class="row2">
                            <div class="field">
                                <label for="new_tire_season">{{ __('Stagionalità') }}</label>
                                <select class="select @error('new_tire_season') is-invalid @enderror" id="new_tire_season"
                                    name="new_tire_season">
                                    <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                                    <option value="summer" {{ old('new_tire_season') == 'summer' ? 'selected' : '' }}>{{ __('Estive') }}</option>
                                    <option value="winter" {{ old('new_tire_season') == 'winter' ? 'selected' : '' }}>{{ __('Invernali') }}</option>
                                    <option value="all_season" {{ old('new_tire_season') == 'all_season' ? 'selected' : '' }}>{{ __('Quattro stagioni') }}</option>
                                </select>
                                @error('new_tire_season')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label for="new_tire_axle">{{ __('Asse') }}</label>
                                <select class="select" id="new_tire_axle" name="new_tire_axle">
                                    <option value="full" {{ old('new_tire_axle', 'full') == 'full' ? 'selected' : '' }}>{{ __('Set completo (4)') }}</option>
                                    <option value="front" {{ old('new_tire_axle') == 'front' ? 'selected' : '' }}>{{ __('Anteriori (2)') }}</option>
                                    <option value="rear" {{ old('new_tire_axle') == 'rear' ? 'selected' : '' }}>{{ __('Posteriori (2)') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row2">
                            <div class="field">
                                <label for="new_tire_brand">{{ __('Marca') }}</label>
                                <input type="text" class="input" id="new_tire_brand" name="new_tire_brand"
                                    value="{{ old('new_tire_brand') }}" placeholder="{{ __('es. Michelin') }}">
                            </div>
                            <div class="field">
                                <label for="new_tire_model_name">{{ __('Modello') }}</label>
                                <input type="text" class="input" id="new_tire_model_name" name="new_tire_model_name"
                                    value="{{ old('new_tire_model_name') }}" placeholder="{{ __('es. Alpin 6') }}">
                            </div>
                        </div>
                        <div class="field">
                            <label for="new_tire_size">{{ __('Misura') }}</label>
                            <input type="text" class="input" id="new_tire_size" name="new_tire_size"
                                value="{{ old('new_tire_size') }}" placeholder="{{ __('es. 205/55 R16') }}">
                        </div>
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <x-form.date-input name="return_date" label="{{ __('Data restituzione veicolo') }}" />
                        <div class="hint">{{ __("Compila per segnare l'appuntamento come completato.") }}</div>
                    </div>
                    <div class="field">
                        <label for="mileage_at_service">{{ __("Chilometraggio all'appuntamento") }}</label>
                        <input type="number" class="input @error('mileage_at_service') is-invalid @enderror"
                            id="mileage_at_service" name="mileage_at_service" value="{{ old('mileage_at_service') }}"
                            min="0" placeholder="es. 87400">
                        @error('mileage_at_service')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="hint">
                            {{ __("Obbligatorio se è selezionato un tagliando — km al momento del conferimento in officina.") }}
                        </div>
                    </div>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3" placeholder="{{ __('Annotazioni facoltative su questo appuntamento...') }}">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="maintenance-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Aggiungi appuntamento') }}
                </button>
                <a href="{{ request('back', route('admin.maintenance-records.index')) }}"
                    class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    {{-- Dialog completamento: appare quando la data di rientro è compilata --}}
    <div class="modal fade" id="completionModal" tabindex="-1" aria-labelledby="completionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="completionModalLabel">{{ __('Completamento appuntamento') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    <p class="hint" style="margin-bottom:12px;">
                        {{ __('Segna quali guasti e scadenze sono stati completati in questo appuntamento.') }}</p>
                    <div id="completion-issues" style="margin-bottom:12px;">
                        <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Guasti') }}</h6>
                        <div id="completion-issues-list"></div>
                    </div>
                    <div id="completion-deadlines" style="margin-bottom:12px;">
                        <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Scadenze') }}</h6>
                        <div id="completion-deadlines-list"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                    <button type="button" class="btn btn-success" id="completion-confirm-btn">{{ __('Conferma') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicle_id');
            const issueSection = document.getElementById('issue-section');
            const noIssueCta = document.getElementById('no-issue-cta');
            const createIssueLink = document.getElementById('create-issue-link');
            const deadlineSection = document.getElementById('deadline-section');
            const noIssueMsg = document.getElementById('no-issue-msg');
            const noDeadlineMsg = document.getElementById('no-deadline-msg');
            const closedIssueSection = document.getElementById('closed-issue-section');
            const noClosedIssueMsg = document.getElementById('no-closed-issue-msg');

            const filterByVehicle = () => {
                const selectedVehicleId = vehicleSelect.value;

                // Filtra guasti
                const issueChecks = document.querySelectorAll('.issue-checkbox');
                let hasVisibleIssue = false;
                issueChecks.forEach(el => {
                    if (el.dataset.vehicleId === selectedVehicleId) {
                        el.style.display = '';
                        hasVisibleIssue = true;
                    } else {
                        el.style.display = 'none';
                        el.querySelector('input').checked = false;
                    }
                });

                if (!selectedVehicleId) {
                    issueSection.style.display = 'none';
                    noIssueCta.style.display = 'none';
                    noIssueMsg.style.display = 'none';
                } else if (!hasVisibleIssue) {
                    issueSection.style.display = '';
                    noIssueCta.style.display = '';
                    noIssueMsg.style.display = '';
                    createIssueLink.href =
                        `{{ route('admin.issues.create') }}?vehicle_id=${selectedVehicleId}&back={{ urlencode(url()->full()) }}`;
                } else {
                    issueSection.style.display = '';
                    noIssueCta.style.display = 'none';
                    noIssueMsg.style.display = 'none';
                }

                // Filtra guasti risolti
                const closedIssueChecks = document.querySelectorAll('.closed-issue-checkbox');
                let hasVisibleClosedIssue = false;
                closedIssueChecks.forEach(el => {
                    if (el.dataset.vehicleId === selectedVehicleId) {
                        el.style.display = '';
                        hasVisibleClosedIssue = true;
                    } else {
                        el.style.display = 'none';
                        el.querySelector('input').checked = false;
                    }
                });

                if (!closedIssueSection) {
                    // Sezione non presente: nessun guasto risolto disponibile.
                } else if (!selectedVehicleId || !hasVisibleClosedIssue) {
                    closedIssueSection.style.display = 'none';
                    noClosedIssueMsg.style.display = 'none';
                } else {
                    closedIssueSection.style.display = '';
                    noClosedIssueMsg.style.display = 'none';
                }

                // Filtra scadenze
                const deadlineChecks = document.querySelectorAll('.deadline-checkbox');
                let hasVisibleDeadline = false;
                deadlineChecks.forEach(el => {
                    if (el.dataset.vehicleId === selectedVehicleId) {
                        el.style.display = '';
                        hasVisibleDeadline = true;
                    } else {
                        el.style.display = 'none';
                        el.querySelector('input').checked = false;
                    }
                });

                if (!selectedVehicleId) {
                    deadlineSection.style.display = 'none';
                    noDeadlineMsg.style.display = 'none';
                } else if (!hasVisibleDeadline) {
                    deadlineSection.style.display = '';
                    noDeadlineMsg.style.display = '';
                } else {
                    deadlineSection.style.display = '';
                    noDeadlineMsg.style.display = 'none';
                }
            };

            filterByVehicle();
            vehicleSelect.addEventListener('change', filterByVehicle);

            // --- Sezione "Cambio Gomme" ---
            const activityTypeSelect = document.getElementById('activity_type');
            const tireSection = document.getElementById('tire-section');
            const noTireMsg = document.getElementById('no-tire-msg');

            const toggleTireSection = () => {
                tireSection.style.display = activityTypeSelect.value === 'Cambio Gomme' ? '' : 'none';
            };
            const filterTiresByVehicle = () => {
                const selectedVehicleId = vehicleSelect.value;
                const tireChecks = document.querySelectorAll('.tire-checkbox');
                let hasVisibleTire = false;
                tireChecks.forEach(el => {
                    if (el.dataset.vehicleId === selectedVehicleId) {
                        el.style.display = '';
                        hasVisibleTire = true;
                    } else {
                        el.style.display = 'none';
                        el.querySelector('input').checked = false;
                    }
                });
                noTireMsg.style.display = (selectedVehicleId && !hasVisibleTire) ? '' : 'none';
            };

            toggleTireSection();
            filterTiresByVehicle();
            activityTypeSelect.addEventListener('change', toggleTireSection);
            vehicleSelect.addEventListener('change', filterTiresByVehicle);

            // --- Dialog completamento ---
            const form = document.getElementById('maintenance-record-form');
            const returnDateInput = document.querySelector('input[name="return_date"]');
            const completionModalEl = document.getElementById('completionModal');
            const completionModal = new bootstrap.Modal(completionModalEl);
            const completionIssuesList = document.getElementById('completion-issues-list');
            const completionDeadlinesList = document.getElementById('completion-deadlines-list');
            const completionConfirmBtn = document.getElementById('completion-confirm-btn');

            // Dati dei guasti e scadenze selezionabili (dal DOM)
            const issueData = [];
            document.querySelectorAll('.issue-checkbox, .closed-issue-checkbox').forEach(el => {
                const input = el.querySelector('input');
                issueData.push({
                    id: input.value,
                    label: el.querySelector('label').textContent.trim(),
                    vehicleId: el.dataset.vehicleId,
                });
            });
            const deadlineData = [];
            document.querySelectorAll('.deadline-checkbox').forEach(el => {
                const input = el.querySelector('input');
                deadlineData.push({
                    id: input.value,
                    label: el.querySelector('label').textContent.trim(),
                    vehicleId: el.dataset.vehicleId,
                });
            });

            let pendingSubmit = false;

            form.addEventListener('submit', function(e) {
                const returnDate = returnDateInput ? returnDateInput.value : '';

                if (!returnDate || pendingSubmit) {
                    return; // nessun dialog, submit normale
                }

                const selectedIssues = issueData.filter(d => {
                    const cb = document.querySelector(`input[name="issue_ids[]"][value="${d.id}"]`);
                    return cb && cb.checked;
                });
                const selectedDeadlines = deadlineData.filter(d => {
                    const cb = document.querySelector(`input[name="deadline_ids[]"][value="${d.id}"]`);
                    return cb && cb.checked;
                });

                completionIssuesList.innerHTML = '';
                if (selectedIssues.length === 0) {
                    document.getElementById('completion-issues').style.display = 'none';
                } else {
                    document.getElementById('completion-issues').style.display = '';
                    selectedIssues.forEach(issue => {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input completion-issue" type="checkbox"
                                value="${issue.id}" id="comp_issue_${issue.id}">
                            <label class="form-check-label" for="comp_issue_${issue.id}">${issue.label}</label>`;
                        completionIssuesList.appendChild(div);
                    });
                }

                completionDeadlinesList.innerHTML = '';
                if (selectedDeadlines.length === 0) {
                    document.getElementById('completion-deadlines').style.display = 'none';
                } else {
                    document.getElementById('completion-deadlines').style.display = '';
                    selectedDeadlines.forEach(deadline => {
                        const div = document.createElement('div');
                        div.className = 'form-check';
                        div.innerHTML = `
                            <input class="form-check-input completion-deadline" type="checkbox"
                                value="${deadline.id}" id="comp_deadline_${deadline.id}">
                            <label class="form-check-label" for="comp_deadline_${deadline.id}">${deadline.label}</label>`;
                        completionDeadlinesList.appendChild(div);
                    });
                }

                e.preventDefault();
                pendingSubmit = true;
                completionModal.show();
            });

            completionConfirmBtn.addEventListener('click', function() {
                form.querySelectorAll('input[name="completed_issue_ids[]"], input[name="completed_deadline_ids[]"]')
                    .forEach(el => el.remove());

                document.querySelectorAll('.completion-issue:checked').forEach(cb => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'completed_issue_ids[]';
                    hidden.value = cb.value;
                    form.appendChild(hidden);
                });
                document.querySelectorAll('.completion-deadline:checked').forEach(cb => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'completed_deadline_ids[]';
                    hidden.value = cb.value;
                    form.appendChild(hidden);
                });

                completionModal.hide();
                pendingSubmit = false;
                form.submit();
            });
        });
    </script>
@endsection
