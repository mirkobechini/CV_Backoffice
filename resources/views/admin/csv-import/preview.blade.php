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
            <input type="hidden" name="rows_json" value="{{ json_encode($results) }}">

            @if ($entity === 'mileage-logs')
                {{-- Vista raggruppata per mese per i chilometraggi --}}
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
                                            <th>#</th>
                                            <th>Veicolo</th>
                                            <th>Km</th>
                                            <th>Stato</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($monthResults as $result)
                                            <tr class="{{ $result['valid'] ? '' : 'table-danger' }}">
                                                <td>{{ $result['row'] }}</td>
                                                <td>
                                                    <strong>{{ $result['data']['_vehicle_label'] ?? ($result['data']['veicolo'] ?? ($result['data']['SIGLA'] ?? '?')) }}</strong>
                                                </td>
                                                <td>{{ number_format($result['data']['_mileage'] ?? 0, 0, ',', '.') }} km
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
                {{-- Vista guasti --}}
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Veicolo</th>
                                <th>Descrizione</th>
                                <th>Data</th>
                                <th>Stato</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr class="{{ $result['valid'] ? '' : 'table-danger' }}">
                                    <td>{{ $result['row'] }}</td>
                                    <td>
                                        <strong>{{ $result['data']['_vehicle_label'] ?? 'N/D' }}</strong>
                                    </td>
                                    <td>{{ $result['data']['_description'] ?? ($result['data']['DESCRIZIONE'] ?? 'N/D') }}
                                    </td>
                                    <td>{{ $result['data']['_date'] ?? ($result['data']['DATA'] ?? 'N/D') }}</td>
                                    <td>
                                        @if (isset($result['data']['_status']))
                                            @if ($result['data']['_status'] === 'closed')
                                                <span class="badge bg-success">Chiuso</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Aperto</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td>
                                        @if (!$result['valid'])
                                            <span class="text-danger small">
                                                @foreach ($result['errors'] as $err)
                                                    <div>{{ $err }}</div>
                                                @endforeach
                                            </span>
                                        @endif
                                        @if (!empty($result['warnings']))
                                            <span class="text-warning small">
                                                @foreach ($result['warnings'] as $warn)
                                                    <div>{{ $warn }}</div>
                                                @endforeach
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($validCount > 0)
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Verranno importati solo i <strong>{{ $validCount }} record validi</strong>.
                    I record con errori verranno saltati.
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> Importa {{ $validCount }} record validi
                </button>
            @else
                <div class="alert alert-danger">
                    Nessun record valido da importare. Correggi gli errori e riprova.
                </div>
            @endif
        </form>
    </div>
@endsection
