@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex align-items-center mb-4">
            <h1 class="mb-0">Chilometraggi</h1>
            <div class="ms-3 pt-2">
                <x-admin.create-button :href="route('admin.mileage-logs.create')" label="chilometraggio" />
                <a href="{{ route('admin.mileage-logs.bulk') }}" class="btn btn-outline-primary btn-sm">Rilevazione
                    mensile</a>
                <a href="{{ route('admin.mileage-logs.pivot') }}" class="btn btn-outline-primary btn-sm">Vista mensile</a>
                <a href="{{ route('admin.csv-import.index') }}" class="btn btn-outline-primary btn-sm">Importa CSV</a>
            </div>
            <div class="ms-auto">
                <a href="{{ route('admin.csv.export', 'mileage-logs') }}" class="btn btn-sm btn-outline-secondary"
                    title="Scarica CSV">
                    <i class="bi bi-download me-1"></i>CSV
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.mileage-logs.bulk-delete') }}" id="bulk-delete-form">
            @csrf
            @method('DELETE')
            <div class="card my-0">
                <table class="table table-striped table-hover my-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px;">
                                <input type="checkbox" id="select-all"
                                    onchange="document.querySelectorAll('.select-item').forEach(c => c.checked = this.checked)">
                            </th>
                            <th>
                                <a href="{{ $sortToggleUrl('vehicle') }}" class="text-decoration-none">
                                    Sigla {!! $sortIcon('vehicle') !!}
                                </a>
                            </th>
                            <th>Targa</th>
                            <th>
                                <a href="{{ $sortToggleUrl('date') }}" class="text-decoration-none">
                                    Data {!! $sortIcon('date') !!}
                                </a>
                            </th>
                            <th>
                                <a href="{{ $sortToggleUrl('km') }}" class="text-decoration-none">
                                    Km {!! $sortIcon('km') !!}
                                </a>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mileageLogs as $mileageLog)
                            <tr>
                                <td><input type="checkbox" class="select-item" name="ids[]" value="{{ $mileageLog->id }}">
                                </td>
                                <td>{{ $mileageLog->vehicle->internal_code }}</td>
                                <td>{{ $mileageLog->vehicle->license_plate }}</td>
                                <td>{{ $mileageLog->log_date_formatted ?? $mileageLog->log_date }}</td>
                                <td>{{ number_format($mileageLog->mileage, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">Nessun chilometraggio registrato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($mileageLogs->isNotEmpty())
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted small">{{ $mileageLogs->count() }} record</span>
                    <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Eliminare i record selezionati?')" id="bulk-delete-btn" disabled>
                        <i class="bi bi-trash"></i> Elimina selezionati
                    </button>
                </div>
            @endif
        </form>
    </div>

    <script>
        document.querySelectorAll('.select-item').forEach(c => {
            c.addEventListener('change', toggleBulkDelete);
        });
        document.getElementById('select-all')?.addEventListener('change', toggleBulkDelete);

        function toggleBulkDelete() {
            const checked = document.querySelectorAll('.select-item:checked').length;
            document.getElementById('bulk-delete-btn').disabled = checked === 0;
        }
    </script>
@endsection
