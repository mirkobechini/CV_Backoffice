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

        {{-- Toolbar sticky con solo pulsante Elimina --}}
        <div class="sticky-top py-2 mb-2" style="z-index:10; background: var(--bs-body-bg);">
            <div class="d-flex align-items-center gap-2 ms-2">
                <button type="button" class="btn btn-danger btn-sm" id="toggle-select-mode">
                    <i class="bi bi-trash"></i>
                </button>
                <span id="bulk-delete-bar" style="display:none;">
                    <button type="button" class="btn btn-danger btn-sm" id="bulk-delete-btn" onclick="bulkDeleteSelected()"
                        disabled>
                        Elimina (<span id="selected-count">0</span>)
                    </button>
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.mileage-logs.bulk-delete') }}" id="bulk-delete-form">
            @csrf
            @method('DELETE')
            <div class="card my-0">
                <table class="table table-striped table-hover my-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:40px; display:none;" class="select-checkbox-col">
                                <input type="checkbox" id="select-all" class="form-check-input"
                                    onchange="document.querySelectorAll('.select-item').forEach(c => { c.checked = this.checked; }); updateSelectedCount();">
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
                                <td style="width:40px; display:none;" class="select-checkbox-col">
                                    <input type="checkbox" class="form-check-input select-item" name="ids[]"
                                        value="{{ $mileageLog->id }}">
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
        </form>
    </div>

    <script>
        document.getElementById('toggle-select-mode').addEventListener('click', function() {
            const selectMode = document.querySelector('.select-checkbox-col').style.display !== 'none';
            document.querySelectorAll('.select-checkbox-col').forEach(el => el.style.display = selectMode ? 'none' :
                '');
            document.getElementById('bulk-delete-bar').style.display = selectMode ? 'none' : 'inline';
            if (!selectMode) {
                document.querySelectorAll('.select-item').forEach(c => c.checked = false);
                document.getElementById('select-all').checked = false;
                updateSelectedCount();
            }
        });

        document.getElementById('select-all')?.addEventListener('change', function() {
            document.querySelectorAll('.select-item').forEach(c => {
                c.checked = this.checked;
            });
            updateSelectedCount();
        });

        document.querySelectorAll('.select-item').forEach(c => {
            c.addEventListener('change', updateSelectedCount);
        });

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.select-item:checked').length;
            document.getElementById('selected-count').textContent = checked;
            document.getElementById('bulk-delete-btn').disabled = checked === 0;
        }

        function bulkDeleteSelected() {
            if (!confirm('Eliminare i record selezionati?')) return;
            document.getElementById('bulk-delete-form').submit();
        }
    </script>
@endsection
