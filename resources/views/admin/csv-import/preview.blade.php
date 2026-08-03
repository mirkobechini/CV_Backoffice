@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <a href="{{ route('admin.csv-import.index') }}" class="btn btn-secondary">Torna all'import</a>
            </div>
        </div>

        <h1 class="mb-4">Anteprima import — {{ $entity === 'issues' ? 'Guasti' : 'Chilometraggi' }}</h1>

        @php
            $validCount = count(array_filter($results, fn($r) => $r['valid']));
            $invalidCount = count($results) - $validCount;
        @endphp

        <div class="alert {{ $invalidCount > 0 ? 'alert-warning' : 'alert-success' }}">
            <strong>{{ count($results) }} righe</strong> trovate:
            <span class="text-success fw-bold">{{ $validCount }} valide</span>
            @if ($invalidCount > 0)
                , <span class="text-danger fw-bold">{{ $invalidCount }} con errori</span>
            @endif
        </div>

        <form action="{{ route('admin.csv-import.confirm') }}" method="POST">
            @csrf
            <input type="hidden" name="entity" value="{{ $entity }}">

            @if ($entity === 'mileage-logs')
                @php
                    $groupedByMonth = collect($results)->groupBy(fn($r) => $r['data']['_label_date'] ?? 'Senza data');
                @endphp

                @foreach ($groupedByMonth as $monthLabel => $monthResults)
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ $monthLabel }}</h5>
                            <span
                                class="badge bg-success">{{ count(array_filter($monthResults->toArray(), fn($r) => $r['valid'])) }}
                                valide</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Salta</th>
                                            <th>Veicolo</th>
                                            <th>Km</th>
                                            <th>Stato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($monthResults as $index => $result)
                                            @php $inputIdx = $loop->parent->index * 1000 + $index; @endphp
                                            <tr class="{{ $result['valid'] ? '' : 'table-danger' }}">
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input skip-item"
                                                        name="editable[{{ $inputIdx }}][_skip]" value="1"
                                                        {{ !$result['valid'] ? 'disabled' : '' }}>
                                                </td>
                                                <td>
                                                    <strong>{{ $result['data']['_vehicle_label'] ?? ($result['data']['veicolo'] ?? ($result['data']['SIGLA'] ?? '?')) }}</strong>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control form-control-sm"
                                                        style="max-width:130px;"
                                                        name="editable[{{ $inputIdx }}][_mileage]"
                                                        value="{{ $result['data']['_mileage'] ?? '' }}"
                                                        {{ !$result['valid'] ? 'disabled' : '' }}>
                                                    <input type="hidden" name="editable[{{ $inputIdx }}][_vehicle_id]"
                                                        value="{{ $result['data']['_vehicle_id'] ?? '' }}">
                                                    <input type="hidden" name="editable[{{ $inputIdx }}][_date]"
                                                        value="{{ $result['data']['_date'] ?? '' }}">
                                                    <input type="hidden" name="editable[{{ $inputIdx }}][_label_date]"
                                                        value="{{ $result['data']['_label_date'] ?? '' }}">
                                                    <input type="hidden" name="editable[{{ $inputIdx }}][_valid]"
                                                        value="{{ $result['valid'] ? '1' : '0' }}">
                                                    <input type="hidden" name="editable[{{ $inputIdx }}][_exists]"
                                                        value="{{ $result['data']['_exists'] ?? '' }}">
                                                </td>
                                                <td>
                                                    @if ($result['valid'])
                                                        <span class="badge bg-success">✅ Valido</span>
                                                    @else
                                                        <span class="badge bg-danger">❌ Errore</span>
                                                        <div class="text-danger small mt-1">
                                                            @foreach ($result['errors'] as $err)
                                                                <div>{{ $err }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @if (!empty($result['warnings']))
                                                        <div class="text-warning small mt-1">
                                                            @foreach ($result['warnings'] as $warn)
                                                                <div>{{ $warn }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Vista guasti con campi editabili --}}
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">Salta</th>
                                <th>#</th>
                                <th>Veicolo</th>
                                <th>Descrizione</th>
                                <th>Data</th>
                                <th>Stato</th>
                                <th>Appuntamento</th>
                                <th>Officina</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $index => $result)
                                <tr class="{{ $result['valid'] ? '' : 'table-danger' }}">
                                    <td class="text-center">
                                        <input type="checkbox" class="form-check-input skip-item"
                                            name="editable[{{ $index }}][_skip]" value="1"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <strong>{{ $result['data']['_vehicle_label'] ?? 'N/D' }}</strong>
                                        <input type="hidden" name="editable[{{ $index }}][_vehicle_id]"
                                            value="{{ $result['data']['_vehicle_id'] ?? '' }}">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" style="min-width:200px;"
                                            name="editable[{{ $index }}][_description]"
                                            value="{{ $result['data']['_description'] ?? ($result['data']['DESCRIZIONE'] ?? '') }}"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" style="max-width:130px;"
                                            name="editable[{{ $index }}][_date]"
                                            value="{{ $result['data']['_date'] ?? ($result['data']['DATA'] ?? '') }}"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" style="max-width:110px;"
                                            name="editable[{{ $index }}][_status]"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                            <option value="open"
                                                {{ ($result['data']['_status'] ?? 'open') == 'open' ? 'selected' : '' }}>
                                                Aperto</option>
                                            <option value="in_progress"
                                                {{ ($result['data']['_status'] ?? '') == 'in_progress' ? 'selected' : '' }}>
                                                In lavorazione</option>
                                            <option value="closed"
                                                {{ ($result['data']['_status'] ?? '') == 'closed' ? 'selected' : '' }}>
                                                Chiuso</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" style="max-width:120px;"
                                            name="editable[{{ $index }}][_appointment_date]"
                                            value="{{ $result['data']['_appointment_date'] ?? '' }}"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" style="max-width:130px;"
                                            name="editable[{{ $index }}][_provider_name]"
                                            value="{{ $result['data']['_provider_name'] ?? '' }}"
                                            {{ !$result['valid'] ? 'disabled' : '' }}>
                                    </td>
                                    <td>

                                        @if ($validCount > 0)
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i>
                                                Puoi modificare i campi direttamente in tabella prima di importare.
                                                Verranno importati solo i <strong>{{ $validCount }} record
                                                    validi</strong>.
                                            </div>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-upload"></i> Importa {{ $validCount }} record
                                            </button>
                                        @else
                                            <div class="alert alert-danger">
                                                Nessun record valido da importare. Correggi gli errori e riprova.
                                            </div>
                                        @endif
        </form>
    </div>
@endsection
