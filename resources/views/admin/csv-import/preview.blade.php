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
            <input type="hidden" name="rows_json" value="{{ json_encode($rows) }}">

            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Validità</th>
                            @php
                                $sample = $results[0]['data'] ?? [];
                                $ignored = [
                                    '_vehicle_id',
                                    '_vehicle_label',
                                    '_status',
                                    '_date',
                                    '_mileage',
                                    '_label_date',
                                ];
                            @endphp
                            @foreach ($sample as $key => $value)
                                @if (!in_array($key, $ignored))
                                    <th>{{ $key }}</th>
                                @endif
                            @endforeach
                            <th>Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            <tr class="{{ $result['valid'] ? '' : 'table-danger' }}">
                                <td>{{ $result['row'] }}</td>
                                <td>
                                    @if ($result['valid'])
                                        <span class="badge bg-success">✅</span>
                                    @else
                                        <span class="badge bg-danger">❌</span>
                                    @endif
                                </td>
                                @foreach ($result['data'] as $key => $value)
                                    @if (!in_array($key, ['_vehicle_id', '_vehicle_label', '_status', '_date', '_mileage', '_label_date']))
                                        <td>{{ $value }}</td>
                                    @endif
                                @endforeach
                                <td>
                                    @if (!empty($result['errors']))
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
                                    @if (isset($result['data']['_vehicle_label']))
                                        <span class="text-muted small">{{ $result['data']['_vehicle_label'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

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
