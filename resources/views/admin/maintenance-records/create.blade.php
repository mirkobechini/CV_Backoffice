@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn btn-secondary">Torna alla
                    pagina precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Aggiungi nuovo appuntamento</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="maintenance-record-form" method="POST" action="{{ route('admin.maintenance-records.store') }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    <section class="mb-3 row">
                        <h2>Dettagli veicolo</h2>
                        <div class="mb-3">
                            <label for="vehicle_id" class="form-label">Veicolo</label>
                            <select class="form-select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                                name="vehicle_id" required>
                                <option value="">Seleziona un veicolo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}"
                                        {{ (string) old('vehicle_id', $preselectedVehicleId ?? '') === (string) $vehicle->id ? 'selected' : '' }}>
                                        {{ $vehicle->internal_code }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3" id="issue-section" style="display: none;">
                            <label for="issue_id" class="form-label">Guasto</label>
                            <select class="form-select @error('issue_id') is-invalid @enderror" id="issue_id"
                                name="issue_id" disabled required>
                                <option value="">Seleziona un guasto</option>
                                @foreach ($openIssues as $issue)
                                    <option value="{{ $issue->id }}" data-vehicle-id="{{ $issue->vehicle_id }}"
                                        {{ (string) old('issue_id', $preselectedIssueId ?? '') === (string) $issue->id ? 'selected' : '' }}>
                                        {{ $issue->description }}</option>
                                @endforeach
                            </select>
                            @error('issue_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="no-issue-cta" style="display: none;">
                            <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
                                <span>Nessun guasto disponibile per il veicolo selezionato.</span>
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
                                        {{ old('provider_id') == $provider->id ? 'selected' : '' }}>
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
                        <div class="mb-3">
                            <label for="appointment_date" class="form-label">Data Appuntamento</label>
                            <input type="date" class="form-control @error('appointment_date') is-invalid @enderror"
                                id="appointment_date" name="appointment_date" value="{{ old('appointment_date') }}"
                                required>
                            @error('appointment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="activity_type" class="form-label">Tipo attività</label>
                            <select class="form-select @error('activity_type') is-invalid @enderror" id="activity_type"
                                name="activity_type" value="{{ old('activity_type') }}">
                                <option value="">Seleziona una tipologia</option>
                                @foreach (\App\Models\MaintenanceRecord::ACTIVITY_TYPES as $item)
                                    <option value="{{ $item }}"
                                        {{ old('activity_type') == $item ? 'selected' : '' }}>
                                        {{ $item }}
                                    </option>
                                @endforeach
                            </select>
                            @error('activity_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="return_date" class="form-label">Data restituzione veicolo</label>
                            <input type="date" class="form-control @error('return_date') is-invalid @enderror"
                                id="return_date" name="return_date" value="{{ old('return_date') }}">
                            @error('return_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="maintenance-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Aggiungi</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const vehicleSelect = document.getElementById('vehicle_id');
            const issueSection = document.getElementById('issue-section');
            const issueSelect = document.getElementById('issue_id');
            const noIssueCta = document.getElementById('no-issue-cta');
            const createIssueLink = document.getElementById('create-issue-link');

            // Filtra i guasti in base al veicolo selezionato e gestisce CTA alternativa.
            const filterIssuesByVehicle = () => {
                const selectedVehicleId = vehicleSelect.value;

                if (!selectedVehicleId) {
                    issueSection.style.display = 'none';
                    noIssueCta.style.display = 'none';
                    issueSelect.disabled = true;
                    issueSelect.value = '';
                    return;
                }

                const options = issueSelect.querySelectorAll('option');
                let hasVisibleIssue = false;

                options.forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const belongsToVehicle = option.dataset.vehicleId === selectedVehicleId;
                    option.hidden = !belongsToVehicle;

                    if (!belongsToVehicle && option.selected) {
                        issueSelect.value = '';
                    }

                    if (belongsToVehicle) {
                        hasVisibleIssue = true;
                    }
                });

                if (!hasVisibleIssue) {
                    issueSection.style.display = 'none';
                    noIssueCta.style.display = '';
                    issueSelect.disabled = true;
                    issueSelect.value = '';
                    createIssueLink.href =
                        `{{ route('admin.issues.create') }}?vehicle_id=${selectedVehicleId}&back={{ urlencode(url()->full()) }}`;
                } else {
                    issueSection.style.display = '';
                    noIssueCta.style.display = 'none';
                    issueSelect.disabled = false;
                }
            };

            filterIssuesByVehicle();
            vehicleSelect.addEventListener('change', filterIssuesByVehicle);

        });
    </script>
@endsection
