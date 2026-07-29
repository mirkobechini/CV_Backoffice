@extends('layouts.app')

@section('content')
    <style>
        .stat-card {
            border-radius: 16px;
            border: none;
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
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-expired {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-ok {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
    <div class="container py-5 bg-light">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>{{ __('Dashboard') }}</h2>
            <span class="text-muted small">Oggi, 29 luglio 2026</span>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-3">
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
        </div>

        <div class="row g-4">

            <!-- COLONNA SINISTRA: Scadenze imminenti -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white border-0 rounded-4 pb-0 pt-3">
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
                                            <strong>{{ $deadline->name }}</strong><br>
                                            <small class="text-muted">{{ $deadline->vehicle->internal_code }} —
                                                Scade tra {{ now()->diffInDays($deadline->due_date) }} giorni</small>
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
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white border-0 rounded-4 pb-0 pt-3">
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
                                            <strong>{{ $issue->title }}cioao</strong><br>
                                            <small class="text-muted">{{ $issue->vehicle->internal_code }} —
                                                {{ $issue->created_at->format('d/m/Y') }}</small>
                                        </div>
                                        <span
                                            class="badge {{ $issue->status === 'open' ? 'bg-danger text-light' : 'bg-warning text-dark' }} rounded-pill px-3 py-2">{{ match ($issue->status) {'open' => 'Aperto','in_progress' => 'In lavorazione','closed' => 'Risolto',default => $issue->status} }}</span>
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
        <div class="row g-4 mt-2">

            <!-- Prossimi appuntamenti -->
            <div class="col-md-6">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white border-0 rounded-4 pb-0 pt-3">
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
                                            <strong>{{ $appointment->description }}</strong><br>
                                            <small class="text-muted">{{ $appointment->vehicle->internal_code }} @
                                                {{ $appointment->workshop_name }}</small>
                                        </div>
                                        <span
                                            class="badge bg-primary rounded-pill px-3 py-2">{{ $appointment->scheduled_date->format('d/m/Y') }}</span>
                                    </div>
                                @endforeach
                                <div class="mt-3 text-center">
                                    <a href="{{ route('admin.maintenance_records.index') }}"
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
            <div class="col-md-3">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white border-0 rounded-4 pb-0 pt-3">
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
                                    style="width: {{ (($totalVehicles - $incompleteVehicles->count()) / $totalVehicles) * 100 }}%">
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

            <!-- Veicoli in warning -->
            <div class="col-md-3">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-white border-0 rounded-4 pb-0 pt-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-exclamation-diamond me-2 text-danger"></i>Da
                            attenzionare</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @if ($incompleteVehicles->isEmpty())
                                <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Nessun veicolo da attenzionare</strong><br>
                                        <small class="text-muted">Tutti i veicoli sono in regola</small>
                                    </div>
                                </div>
                            @else
                                @foreach ($incompleteVehicles as $vehicle)
                                    <div
                                        class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                        <span><i
                                                class="bi bi-truck text-warning me-2"></i>{{ $vehicle->internal_code }}</span>
                                        <span class="badge bg-warning text-dark rounded-pill">Equipaggiamento da
                                            integrare</span>
                                    </div>
                                @endforeach
                                <a href="{{ route('admin.vehicles.index') }}"
                                    class="btn btn-outline-secondary btn-sm rounded-pill mt-2 w-100">Vedi
                                    tutti i
                                    veicoli</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
