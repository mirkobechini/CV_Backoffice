@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Chilometraggi')],
    ]" />
@endsection

@section('content')

    {{-- Barra di selezione multipla (nascosta finché non si attiva la modalità selezione) --}}
    <div class="bulk-bar" id="bulk-delete-bar" style="display:none;">
        <button type="button" class="btn danger" id="bulk-delete-btn" disabled>
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }} (<span id="selected-count">0</span>)
        </button>
        <span class="txt">{{ __('Seleziona le righe da eliminare') }}</span>
    </div>

    <form method="POST" action="{{ route('admin.mileage-logs.bulk-delete') }}" id="bulk-delete-form">
        @csrf
        @method('DELETE')

        <div class="table-card">
            <div class="toolbar">
                <div class="toolbar-left">
                    <h2>{{ __('Elenco chilometraggi') }}</h2>
                </div>
                <div class="filters">
                    <button type="button" class="btn" id="toggle-select-mode">
                        <i class="fa-solid fa-check-double"></i> {{ __('Seleziona') }}
                    </button>
                    <a href="{{ route('admin.mileage-logs.bulk') }}" class="btn">
                        <i class="fa-solid fa-calendar-days"></i> {{ __('Rilevazione mensile') }}
                    </a>
                    <a href="{{ route('admin.mileage-logs.pivot') }}" class="btn">
                        <i class="fa-solid fa-table-cells"></i> {{ __('Vista mensile') }}
                    </a>
                    <a href="{{ route('admin.csv-import.index') }}" class="btn">
                        <i class="fa-solid fa-file-import"></i> {{ __('Importa CSV') }}
                    </a>
                    <a href="{{ route('admin.csv.export', 'mileage-logs') }}" class="btn" title="{{ __('Scarica CSV') }}">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                    <a href="{{ route('admin.mileage-logs.create') }}" class="btn primary">
                        <i class="fa-solid fa-plus"></i> {{ __('Nuovo chilometraggio') }}
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th class="select-checkbox-col select-col" style="display:none;">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th>
                                <div class="th-wrap"><span>{{ __('Sigla') }}</span>
                                    <a href="{{ $sortToggleUrl('vehicle') }}"
                                        class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                        title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                                </div>
                            </th>
                            <th>{{ __('Targa') }}</th>
                            <th>
                                <div class="th-wrap"><span>{{ __('Data') }}</span>
                                    <a href="{{ $sortToggleUrl('date') }}"
                                        class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                        title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                                </div>
                            </th>
                            <th>
                                <div class="th-wrap"><span>{{ __('Km') }}</span>
                                    <a href="{{ $sortToggleUrl('km') }}" class="mini {{ $sortBy === 'km' ? 'on' : '' }}"
                                        title="{{ __('Ordina per km') }}">{{ $sortIcon('km') }}</a>
                                </div>
                            </th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mileageLogs as $mileageLog)
                            <tr>
                                <td class="select-checkbox-col select-col" style="display:none;">
                                    <input type="checkbox" class="select-item" name="ids[]"
                                        value="{{ $mileageLog->id }}">
                                </td>
                                <td class="code">{{ $mileageLog->vehicle->internal_code ?? 'N/A' }}</td>
                                <td class="code">{{ $mileageLog->vehicle->license_plate ?? 'N/A' }}</td>
                                <td>{{ $mileageLog->log_date_formatted ?? 'N/A' }}</td>
                                <td>
                                    <div class="km-value">{{ number_format($mileageLog->mileage, 0, ',', '.') }}</div>
                                    @php($delta = $deltaByLogId[$mileageLog->id] ?? null)
                                    @if ($delta !== null)
                                        <div class="cell-sub">
                                            {{ $delta >= 0 ? '+' : '' }}{{ number_format($delta, 0, ',', '.') }}
                                            {{ __('dal mese scorso') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.mileage-logs.show', $mileageLog->id) }}" class="mini-btn"
                                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.mileage-logs.edit', $mileageLog->id) }}" class="mini-btn"
                                            title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                        <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal-{{ $mileageLog->id }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty">{{ __('Nessun chilometraggio registrato.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    {{-- I modal di conferma eliminazione vanno fuori dal form di selezione multipla:
    un <form> non può contenere altri <form> annidati (HTML non lo permette e il
    browser scarta silenziosamente quello interno), quindi qui restano fuori. --}}
    @foreach ($mileageLogs as $mileageLog)
        <x-admin.delete-modal type="mileageLog" :object="$mileageLog" />
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggle-select-mode');
            const bulkBar = document.getElementById('bulk-delete-bar');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
            const selectAll = document.getElementById('select-all');
            const selectedCount = document.getElementById('selected-count');
            const bulkForm = document.getElementById('bulk-delete-form');

            function updateSelectedCount() {
                const checked = document.querySelectorAll('.select-item:checked').length;
                selectedCount.textContent = checked;
                bulkDeleteBtn.disabled = checked === 0;
            }

            toggleBtn.addEventListener('click', function() {
                const isSelectMode = document.querySelector('.select-checkbox-col').style.display !== 'none';
                document.querySelectorAll('.select-checkbox-col').forEach(el => el.style.display = isSelectMode ?
                    'none' : '');
                bulkBar.style.display = isSelectMode ? 'none' : 'flex';
                toggleBtn.classList.toggle('primary', !isSelectMode);
                if (isSelectMode) {
                    document.querySelectorAll('.select-item').forEach(c => c.checked = false);
                    selectAll.checked = false;
                    updateSelectedCount();
                }
            });

            selectAll?.addEventListener('change', function() {
                document.querySelectorAll('.select-item').forEach(c => c.checked = this.checked);
                updateSelectedCount();
            });

            document.querySelectorAll('.select-item').forEach(c => c.addEventListener('change', updateSelectedCount));

            bulkDeleteBtn.addEventListener('click', function() {
                if (!confirm('{{ __('Eliminare i record selezionati?') }}')) return;
                bulkForm.submit();
            });
        });
    </script>
@endsection
