@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn btn-secondary">Torna alla
                    pagina precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica appuntamento</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="maintenance-record-form" method="POST"
                    action="{{ route('admin.maintenance-records.update', $maintenanceRecord->id) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli veicolo</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        {{ old('vehicle_id', $maintenanceRecord->vehicle_id) == $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3" id="issue-section" style="display: none;">
                            <label class="form-label">Guasti collegati</label>
                            <div class="border rounded p-3 bg-body-secondary" id="issue-checkboxes">
                                @error('issue_ids')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @php
                                    $linkedIssueIds = $maintenanceRecord->items
                                        ->where('itemable_type', 'App\\Models\\Issue')
                                        ->pluck('itemable_id')
                                        ->map(fn($v) => (string) $v)
                                        ->toArray();
                                @endphp
                                @foreach ($openIssues as $issue)
                                    <div class="form-check issue-checkbox" data-vehicle-id="{{ $issue->vehicle_id }}"
                                        style="display: none;">
                                        <input class="form-check-input" type="checkbox" name="issue_ids[]"
                                            value="{{ $issue->id }}" id="edit_issue_{{ $issue->id }}"
                                            {{ in_array((string) $issue->id, old('issue_ids', $linkedIssueIds)) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="edit_issue_{{ $issue->id }}">
                                            {{ $issue->description }}
                                            @if ($issue->event_date)
                                                — {{ $issue->event_date->format('d/m/Y') }}
                                            @endif
                                            @if ($issue->status !== 'open' && $issue->status !== 'in_progress')
                                                <span class="badge bg-warning text-dark">({{ $issue->status }})</span>
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="alert alert-info mt-2 d-none" id="no-issue-msg">
                                <small>Nessun guasto aperto per il veicolo selezionato.</small>
                            </div>
                        </div>

                        @if ($closedIssues->isNotEmpty())
                            <div class="mb-3" id="closed-issue-section" style="display: none;">
                                <label class="form-label">Guasti risolti (per registrare riparazioni avvenute)</label>
                                <div class="border rounded p-3 bg-body-secondary" id="closed-issue-checkboxes">
                                    @foreach ($closedIssues as $issue)
                                        <div class="form-check closed-issue-checkbox"
                                            data-vehicle-id="{{ $issue->vehicle_id }}" style="display: none;">
                                            <input class="form-check-input" type="checkbox" name="issue_ids[]"
                                                value="{{ $issue->id }}" id="edit_closed_issue_{{ $issue->id }}">
                                            <label class="form-check-label" for="edit_closed_issue_{{ $issue->id }}">
                                                {{ $issue->description }}
                                                @if ($issue->event_date)
                                                    — {{ $issue->event_date->format('d/m/Y') }}
                                                @endif
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="alert alert-info mt-2 d-none" id="no-closed-issue-msg">
                                    <small>Nessun guasto risolto per il veicolo selezionato.</small>
                                </div>
                            </div>
                        @endif

                        <div class="mb-3" id="deadline-section" style="display: none;">
                            <label class="form-label">Scadenze collegate</label>
                            <div class="border rounded p-3 bg-body-secondary" id="deadline-checkboxes">
                                @error('deadline_ids')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @php
                                    $linkedDeadlineIds = $maintenanceRecord->items
                                        ->where('itemable_type', 'App\\Models\\Deadline')
                                        ->pluck('itemable_id')
                                        ->map(fn($v) => (string) $v)
                                        ->toArray();
                                @endphp
                                @foreach ($pendingDeadlines as $deadline)
                                    <div class="form-check deadline-checkbox" data-vehicle-id="{{ $deadline->vehicle_id }}"
                                        style="display: none;">
                                        <input class="form-check-input" type="checkbox" name="deadline_ids[]"
                                            value="{{ $deadline->id }}" id="edit_deadline_{{ $deadline->id }}"
                                            {{ in_array((string) $deadline->id, old('deadline_ids', $linkedDeadlineIds)) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="edit_deadline_{{ $deadline->id }}">
                                            {{ ucfirst($deadline->type) }} —
                                            {{ $deadline->due_date?->format('d/m/Y') ?? 'N/A' }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="alert alert-info mt-2 d-none" id="no-deadline-msg">
                                <small>Nessuna scadenza in sospeso per il veicolo selezionato.</small>
                            </div>
                        </div>

                        <div class="mb-3" id="no-issue-cta" style="display: none;">
                            <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
                                <span>Nessun guasto aperto per il veicolo selezionato.</span>
                                <a id="create-issue-link" class="btn btn-sm btn-primary"
                                    href="{{ route('admin.issues.create', ['back' => url()->full()]) }}">
                                    Crea guasto
                                </a>
                            </div>
                        </div>
                    </section>
                    <section class="mb-3 row">
                        <h2>Dettagli officina</h2>
                        <div class="mb-3">
                            <label for="provider_id" class="form-label">Officina</label>
                            <select class="form-select @error('provider_id') is-invalid @enderror" id="provider_id"
                                name="provider_id" required>
                                <option value="">Seleziona un'officina</option>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->id }}"
                                        {{ old('provider_id', $maintenanceRecord->provider_id) == $provider->id ? 'selected' : '' }}>
                                        {{ $provider->name }}</option>
                                @endforeach
                            </select>
                            @error('provider_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <section class="mb-3 row">
                        <h2>Dettagli Appuntamento</h2>
                        <x-form.date-input name="appointment_date" label="Data Appuntamento" :model="$maintenanceRecord" required />
                        <div class="mb-3">
                            <label for="activity_type" class="form-label">Tipo attività</label>
                            <select class="form-select @error('activity_type') is-invalid @enderror" id="activity_type"
                                name="activity_type"
                                value="{{ old('activity_type', $maintenanceRecord->activity_type) }}">
                                <option value="">Seleziona una tipologia</option>
                                @foreach (\App\Models\MaintenanceRecord::ACTIVITY_TYPES as $item)
                                    <option value="{{ $item }}"
                                        {{ old('activity_type', $maintenanceRecord->activity_type) == $item ? 'selected' : '' }}>
                                        {{ $item }}
                                    </option>
                                @endforeach
                            </select>
                            @error('activity_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <x-form.date-input name="return_date" label="Data restituzione veicolo" :model="$maintenanceRecord" />
                        <div class="mb-3">
                            <label for="mileage_at_service" class="form-label">Chilometraggio all'appuntamento</label>
                            <input type="number" class="form-control @error('mileage_at_service') is-invalid @enderror"
                                id="mileage_at_service" name="mileage_at_service"
                                value="{{ old('mileage_at_service', $maintenanceRecord->mileage_at_service) }}"
                                min="0">
                            @error('mileage_at_service')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Obbligatorio se è selezionato un tagliando — km al momento del
                                conferimento in officina.</div>
                        </div>
                    </section>
                    <button id="maintenance-submit-btn" type="button" class="btn btn-primary" data-bs-toggle="modal"
                        data-bs-target="#confirmMaintenanceUpdateModal" data-loading-text="Salvataggio...">Salva
                        modifiche</button>
                </form>
            </div>
        </div>

        <div class="modal fade" id="confirmMaintenanceUpdateModal" tabindex="-1"
            aria-labelledby="confirmMaintenanceUpdateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmMaintenanceUpdateModalLabel">Conferma aggiornamento intervento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-2"><strong>Il guasto è stato aggiustato?</strong></p>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="issue_resolved"
                                id="edit_issue_resolved_yes" value="1" form="maintenance-record-form">
                            <label class="form-check-label" for="edit_issue_resolved_yes">Sì</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="issue_resolved"
                                id="edit_issue_resolved_no" value="0" form="maintenance-record-form">
                            <label class="form-check-label" for="edit_issue_resolved_no">No</label>
                        </div>
                        <small class="text-muted d-block mt-2">Se non selezioni nulla, lo stato del guasto resta
                            invariato.</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-primary" form="maintenance-record-form">Conferma e
                            salva</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Dialog completamento: appare quando la data di rientro è compilata --}}
        <div class="modal fade" id="completionModal" tabindex="-1" aria-labelledby="completionModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="completionModalLabel">Completamento appuntamento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Segna quali guasti e scadenze sono stati completati in questo
                            appuntamento.</p>
                        <div id="completion-issues" class="mb-3">
                            <h6 class="fw-bold">Guasti</h6>
                            <div id="completion-issues-list"></div>
                        </div>
                        <div id="completion-deadlines" class="mb-3">
                            <h6 class="fw-bold">Scadenze</h6>
                            <div id="completion-deadlines-list"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="button" class="btn btn-success" id="completion-confirm-btn">Conferma</button>
                    </div>
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
                    noIssueMsg.classList.add('d-none');
                } else if (!hasVisibleIssue) {
                    issueSection.style.display = '';
                    noIssueCta.style.display = '';
                    noIssueMsg.classList.remove('d-none');
                    createIssueLink.href =
                        `{{ route('admin.issues.create') }}?vehicle_id=${selectedVehicleId}&back={{ urlencode(url()->full()) }}`;
                } else {
                    issueSection.style.display = '';
                    noIssueCta.style.display = 'none';
                    noIssueMsg.classList.add('d-none');
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
                } else if (!selectedVehicleId) {
                    closedIssueSection.style.display = 'none';
                    noClosedIssueMsg.classList.add('d-none');
                } else if (!hasVisibleClosedIssue) {
                    closedIssueSection.style.display = '';
                    noClosedIssueMsg.classList.remove('d-none');
                } else {
                    closedIssueSection.style.display = '';
                    noClosedIssueMsg.classList.add('d-none');
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
                    noDeadlineMsg.classList.add('d-none');
                } else if (!hasVisibleDeadline) {
                    deadlineSection.style.display = '';
                    noDeadlineMsg.classList.remove('d-none');
                } else {
                    deadlineSection.style.display = '';
                    noDeadlineMsg.classList.add('d-none');
                }
            };

            filterByVehicle();
            vehicleSelect.addEventListener('change', filterByVehicle);

            // --- Dialog completamento ---
            const form = document.getElementById('maintenance-record-form');
            const returnDateInput = document.querySelector('input[name="return_date"]');
            const completionModalEl = document.getElementById('completionModal');
            const completionModal = new bootstrap.Modal(completionModalEl);
            const completionIssuesList = document.getElementById('completion-issues-list');
            const completionDeadlinesList = document.getElementById('completion-deadlines-list');
            const completionConfirmBtn = document.getElementById('completion-confirm-btn');
            const confirmUpdateModalEl = document.getElementById('confirmMaintenanceUpdateModal');
            const confirmUpdateModal = bootstrap.Modal.getInstance(confirmUpdateModalEl) || new bootstrap.Modal(
                confirmUpdateModalEl);

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

            // Intercetta il submit del form (dal modal di conferma)
            form.addEventListener('submit', function(e) {
                const returnDate = returnDateInput ? returnDateInput.value : '';

                if (!returnDate || pendingSubmit) {
                    return; // nessun dialog, submit normale
                }

                // Popola il dialog con i guasti e le scadenze selezionati
                const selectedIssues = issueData.filter(d => {
                    const cb = document.querySelector(`input[name="issue_ids[]"][value="${d.id}"]`);
                    return cb && cb.checked;
                });
                const selectedDeadlines = deadlineData.filter(d => {
                    const cb = document.querySelector(
                        `input[name="deadline_ids[]"][value="${d.id}"]`);
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
                        div.innerHTML =
                            `
                            <input class="form-check-input completion-deadline" type="checkbox"
                                value="${deadline.id}" id="comp_deadline_${deadline.id}">
                            <label class="form-check-label" for="comp_deadline_${deadline.id}">${deadline.label}</label>`;
                        completionDeadlinesList.appendChild(div);
                    });
                }

                e.preventDefault();
                pendingSubmit = true;
                confirmUpdateModal.hide();
                completionModal.show();
            });

            completionConfirmBtn.addEventListener('click', function() {
                // Rimuovi eventuali hidden input precedenti
                form.querySelectorAll(
                        'input[name="completed_issue_ids[]"], input[name="completed_deadline_ids[]"]')
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
