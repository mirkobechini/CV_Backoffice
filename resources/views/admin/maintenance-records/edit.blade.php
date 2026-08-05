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
                                name="activity_type" value="{{ old('activity_type', $maintenanceRecord->activity_type) }}">
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
                            <div class="form-text">Opzionale — km al momento del conferimento in officina.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ricorrenza (prossimo tagliando)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="number"
                                            class="form-control @error('recurrence_months') is-invalid @enderror"
                                            id="recurrence_months" name="recurrence_months"
                                            value="{{ old('recurrence_months', $maintenanceRecord->recurrence_months) }}"
                                            min="1" max="120" placeholder="es. 12">
                                        <span class="input-group-text">mesi</span>
                                        @error('recurrence_months')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="number"
                                            class="form-control @error('recurrence_km') is-invalid @enderror"
                                            id="recurrence_km" name="recurrence_km"
                                            value="{{ old('recurrence_km', $maintenanceRecord->recurrence_km) }}"
                                            min="100" max="500000" placeholder="es. 30000">
                                        <span class="input-group-text">km</span>
                                        @error('recurrence_km')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">Imposta intervallo se il tagliando è ricorrente (es. ogni 12 mesi o
                                30.000 km).</div>
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
        });
    </script>
@endsection
