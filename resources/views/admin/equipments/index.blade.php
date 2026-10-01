@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature')],
    ]" />
@endsection

@section('content')
    @php
        $statusFilterUrl = fn($status) => route('admin.equipments.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
    @endphp

    {{-- Barra revisione/collaudo multipla: fuori dalla tabella (niente form
    annidate con quelle dei modal di eliminazione), compilata via JS dagli
    id selezionati prima dell'invio. --}}
    <form id="equipment-bulk-form" method="POST" action="{{ route('admin.equipments.bulk-record-revision') }}"
        class="bulk-edit-bar" style="display:none;" data-single-submit="true">
        @csrf
        <div id="equipment-bulk-hidden-ids"></div>
        <div class="bulk-edit-bar-inner">
            <span id="equipment-bulk-count" class="bulk-edit-count"></span>
            <div class="field">
                <select class="select sm" name="kind" required>
                    <option value="revision">{{ __('Revisione') }}</option>
                    <option value="collaudo">{{ __('Collaudo') }}</option>
                </select>
            </div>
            <x-form.date-input name="performed_date" label="{{ __('Data effettuata') }}" required />
            <button type="submit" class="btn primary sm">{{ __('Registra per selezionate') }}</button>
            <button type="button" id="equipment-bulk-clear" class="btn ghost sm">{{ __('Annulla selezione') }}</button>
        </div>
        @error('equipment_ids')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @error('performed_date')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </form>

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco attrezzature') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutte') }}</a>
                    <a href="{{ $statusFilterUrl('pending') }}"
                        class="chip {{ $statusFilter === 'pending' ? 'on' : '' }}">{{ __('In scadenza') }}</a>
                    <a href="{{ $statusFilterUrl('expired') }}"
                        class="chip {{ $statusFilter === 'expired' ? 'on' : '' }}">{{ __('Scadute') }}</a>
                    <a href="{{ $statusFilterUrl('valid') }}"
                        class="chip {{ $statusFilter === 'valid' ? 'on' : '' }}">{{ __('Valide') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.equipments.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca nome o seriale') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.equipments.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.csv.export', 'equipments') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.equipments.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova attrezzatura') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="equipment-select-all" title="{{ __('Seleziona tutte (pagina corrente)') }}"></th>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Numero Seriale') }}</th>
                        <th>{{ __('Data di revisione') }}</th>
                        <th>{{ __('Prossimo collaudo') }}</th>
                        <th>{{ __('Sigla') }}</th>
                        <th>{{ __('Targa') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipments as $equipment)
                        <tr>
                            <td><input type="checkbox" class="equipment-select" value="{{ $equipment->id }}"></td>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($equipment->id % 5) + 1 }}"><i
                                            class="fa-solid fa-fire-extinguisher"></i></span>
                                    <div class="vin-name">{{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
                                    </div>
                                </div>
                            </td>
                            <td class="code">{{ $equipment->serial_number ?: '—' }}</td>
                            <td>
                                {{ $equipment->expiration_date_formatted ?? '—' }}
                                @if ($equipment->expiration_date)
                                    <div class="cell-sub">
                                        @php($daysDiff = \Carbon\Carbon::today()->diffInDays($equipment->expiration_date, false))
                                        @if ($daysDiff < 0)
                                            {{ __('scaduta da :n gg', ['n' => abs($daysDiff)]) }}
                                        @else
                                            {{ __('scade tra :n gg', ['n' => $daysDiff]) }}
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($equipment->next_collaudo_date)
                                    {{ $equipment->next_collaudo_date_formatted }}
                                    <div class="cell-sub">
                                        @php($collaudoDaysDiff = \Carbon\Carbon::today()->diffInDays($equipment->next_collaudo_date, false))
                                        @if ($collaudoDaysDiff < 0)
                                            {{ __('scaduto da :n gg', ['n' => abs($collaudoDaysDiff)]) }}
                                        @else
                                            {{ __('scade tra :n gg', ['n' => $collaudoDaysDiff]) }}
                                        @endif
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="code">{{ $equipment->vehicle?->internal_code ?? 'N/A' }}</td>
                            <td class="code">{{ $equipment->vehicle?->license_plate ?? 'N/A' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.equipments.show', $equipment->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.equipments.edit', $equipment->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="equipment" :object="$equipment" />
                    @empty
                        <tr>
                            <td colspan="8" class="empty">{{ __('Nessuna attrezzatura trovata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($equipments->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $equipments->currentPage() }} / {{ $equipments->lastPage() }} ·
                    {{ $equipments->total() }}</span>
                <nav>
                    {{ $equipments->links() }}
                </nav>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('equipment-select-all');
                const checkboxes = () => Array.from(document.querySelectorAll('.equipment-select'));
                const bar = document.getElementById('equipment-bulk-form');
                const countLabel = document.getElementById('equipment-bulk-count');
                const hiddenContainer = document.getElementById('equipment-bulk-hidden-ids');
                const clearBtn = document.getElementById('equipment-bulk-clear');

                const updateBar = () => {
                    const selected = checkboxes().filter(c => c.checked);
                    if (selected.length > 0) {
                        bar.style.display = '';
                        countLabel.textContent = selected.length === 1
                            ? '{{ __('1 attrezzatura selezionata') }}'
                            : selected.length + ' {{ __('attrezzature selezionate') }}';
                    } else {
                        bar.style.display = 'none';
                    }
                };

                checkboxes().forEach(cb => cb.addEventListener('change', updateBar));

                selectAll?.addEventListener('change', () => {
                    checkboxes().forEach(cb => cb.checked = selectAll.checked);
                    updateBar();
                });

                clearBtn?.addEventListener('click', () => {
                    checkboxes().forEach(cb => cb.checked = false);
                    if (selectAll) selectAll.checked = false;
                    updateBar();
                });

                bar?.addEventListener('submit', () => {
                    hiddenContainer.innerHTML = '';
                    checkboxes().filter(c => c.checked).forEach(c => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'equipment_ids[]';
                        input.value = c.value;
                        hiddenContainer.appendChild(input);
                    });
                });
            });
        </script>
    @endpush
@endsection
