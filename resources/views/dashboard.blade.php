@extends('layouts.app')

@section('content')
    <style>
        .stat-card {
            border-radius: 16px;
            border: var(--bs-border-width) solid var(--bs-border-color);
            transition: transform .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .badge-expiring {
            background-color: var(--bs-warning-bg-subtle);
            color: var(--bs-warning-text-emphasis);
        }

        .badge-expired {
            background-color: var(--bs-danger-bg-subtle);
            color: var(--bs-danger-text-emphasis);
        }

        .badge-ok {
            background-color: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }
    </style>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>{{ __('Dashboard') }}</h2>
            <span class="text-muted small">Oggi, {{ \Carbon\Carbon::now()->locale('it')->translatedFormat('j F Y') }}</span>
        </div>
        <div class="row row-cols-md-4 g-3 mb-4">
            <div class="col-6">
                <div class="card stat-card shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-truck"></i></div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $totalVehicles }}</div>
                            <div class="text-muted small">Veicoli totali</div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="col-6">
                <div class="card stat-card shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i
                                class="bi bi-exclamation-triangle"></i></div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $openIssues->count() }}</div>
                            <div class="text-muted small">Guasti aperti</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card stat-card shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-calendar-check"></i>
                        </div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $upcomingDeadlines->count() }}</div>
                            <div class="text-muted small">Scadenze imminenti</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card stat-card shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-wrench"></i></div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $upcomingAppointments->count() }}</div>
                            <div class="text-muted small">In officina</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card stat-card shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-tools"></i></div>
                        <div>
                            <div class="fs-3 fw-bold">{{ $expiringEquipment->count() }}</div>
                            <div class="text-muted small">Attrez. in scadenza</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-md-2 g-4">

            <!-- COLONNA SINISTRA: Scadenze imminenti -->
            <div class="col">
                <div class="card shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-warning"></i>Scadenze imminenti
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($upcomingDeadlines->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessuna scadenza imminente</strong><br>
                                        <small class="text-muted">Tutti i veicoli sono in regola</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($upcomingDeadlines as $deadline)
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $deadline->type }}</strong><br>
                                            <small class="text-muted">{{ $deadline->vehicle->internal_code }} —
                                                Scade tra {{ floor(now()->diffInDays($deadline->due_date,false)) }} giorni</small>
                                        </div>
                                        <span
                                            class="badge badge-expiring rounded-pill px-3 py-2">{{ $deadline->due_date->format('d/m/Y') }}</span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.deadlines.index') }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Vedi tutte
                                        le
                                        scadenze <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLONNA DESTRA: Guasti aperti -->
            <div class="col">
                <div class="card shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-circle me-2 text-danger"></i>Guasti aperti
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($openIssues->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessun guasto aperto</strong><br>
                                        <small class="text-muted">Tutti i veicoli sono funzionanti</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($openIssues as $issue)
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $issue->description }}</strong><br>
                                            <small class="text-muted">{{ $issue->vehicle->internal_code }} —
                                                {{ $issue->event_date->format('d/m/Y') }}</small>
                                        </div>
                                        <span
                                            class="badge {{ match ($issue->status_color) {'red' => 'bg-danger text-light','yellow' => 'bg-warning text-dark','green' => 'bg-success',default => 'bg-secondary'} }} rounded-pill px-3 py-2">{{ $issue->status_label }}</span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.issues.index') }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Vedi tutti i guasti
                                        <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row row-cols-md-2 g-4 mt-2">

            <!-- Prossimi appuntamenti -->
            <div class="col">
                <div class="card shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-tools me-2 text-primary"></i>Prossimi appuntamenti in
                            officina</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($upcomingAppointments->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessun appuntamento imminente</strong><br>
                                        <small class="text-muted">Tutti i veicoli sono in regola</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($upcomingAppointments as $appointment)
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $appointment->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable?->description ?? $appointment->activity_type }}</strong><br>
                                            <small class="text-muted">{{ $appointment->vehicle->internal_code }} @
                                                {{ $appointment->provider?->name }}</small>
                                        </div>
                                        <span
                                            class="badge bg-primary rounded-pill px-3 py-2">{{ $appointment->appointment_date->format('d/m/Y') }}</span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.maintenance-records.index') }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Vedi tutti
                                        gli
                                        appuntamenti <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipaggiamento da integrare -->
            <div class="col">
                <div class="card shadow-sm rounded-4 h-100">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-backpack me-2 text-success"></i>Equipaggiamento</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold fs-4">{{ $totalVehicles - $incompleteVehicles->count() }}</span>
                                <span class="text-muted">/ {{ $totalVehicles }} mezzi</span>
                            </div>
                            <div class="progress mt-1" style="height: 8px;">
                                <div class="progress-bar bg-success"
                                    style="width: {{ $totalVehicles > 0 ? (($totalVehicles - $incompleteVehicles->count()) / $totalVehicles) * 100 : 0 }}%">
                                </div>
                            </div>
                            <small class="text-muted">completi</small>
                        </div>
                        <div class="mt-auto">
                            <div class="alert alert-warning py-2 mb-2 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                {{ $incompleteVehicles->count() }} mezzo/i con equipaggiamento da integrare
                            </div>
                        </div>
                        <a href="{{ route('admin.vehicles.index') }}"
                            class="btn btn-outline-secondary btn-sm rounded-pill mt-2 w-100">Dettagli</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-tools me-2 text-info"></i>Attrezzature in scadenza</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($expiringEquipment->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessuna attrezzatura in scadenza</strong><br>
                                        <small class="text-muted">Tutte le attrezzature sono in regola</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($expiringEquipment as $equipment)
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $equipment->name }}</strong><br>
                                            <small class="text-muted">{{ $equipment->vehicle->internal_code }} —
                                                {{ $equipment->equipmentType?->name ?? 'N/A' }}</small>
                                        </div>
                                        <span
                                            class="badge {{ $equipment->expiration_date->isPast() ? 'bg-danger text-light' : 'bg-warning text-dark' }} rounded-pill px-3 py-2">
                                            {{ $equipment->expiration_date->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.equipments.index') }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Vedi tutte le
                                        attrezzature <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGA AGGIUNTIVA: Attrezzature in scadenza -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm rounded-4">
                    <div class="card-header bg-transparent border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-shield-exclamation me-2 text-warning"></i>Attrezzature in
                            scadenza</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($expiringEquipment->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessuna attrezzatura in scadenza</strong><br>
                                        <small class="text-muted">Tutti gli equipaggiamenti sono in regola</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($expiringEquipment as $equipment)
                                    <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $equipment->name }}</strong>
                                            @if ($equipment->equipmentType)
                                                <span
                                                    class="badge bg-secondary ms-1">{{ $equipment->equipmentType->name }}</span>
                                            @endif
                                            <br>
                                            <small class="text-muted">{{ $equipment->vehicle?->internal_code ?? 'N/A' }} —
                                                Scadenza: {{ $equipment->expiration_date->format('d/m/Y') }}</small>
                                        </div>
                                        <span
                                            class="badge {{ $equipment->expiration_date->isPast() ? 'bg-danger' : ($equipment->expiration_date->diffInDays(now()) <= 30 ? 'bg-warning text-dark' : 'bg-success') }} rounded-pill px-3 py-2">
                                            {{ $equipment->expiration_date->isPast() ? 'Scaduta' : ($equipment->expiration_date->diffInDays(now()) <= 30 ? 'In scadenza' : 'Valida') }}
                                        </span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.equipments.index') }}"
                                        class="btn btn-outline-secondary btn-sm rounded-pill px-4">Vedi tutte le
                                        attrezzature
                                        <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
