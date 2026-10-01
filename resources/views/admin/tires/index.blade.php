@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Pneumatici')],
    ]" />
@endsection

@section('content')
    @php
        $badgeClass = fn($status) => match ($status) {
            'mounted' => 'b-green',
            'stored' => 'b-gray',
            'retired' => 'b-red',
            default => 'b-gray',
        };
        $statusFilterUrl = fn($status) => route('admin.tires.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
        $seasonDueUrl = route('admin.tires.index', array_merge(request()->except(['season_filter', 'page']), $seasonFilter === 'due' ? [] : ['season_filter' => 'due']));
        $seasonLabels = ['summer' => __('Estive'), 'winter' => __('Invernali'), 'all_season' => __('Quattro stagioni')];
    @endphp

    {{-- Barra modifica di gruppo: fuori dalla tabella (niente form annidate
    con quelle dei modal di eliminazione), compilata via JS dagli id
    selezionati prima dell'invio. --}}
    <form id="tire-bulk-form" method="POST" action="{{ route('admin.tires.bulk-update') }}"
        class="bulk-edit-bar" style="display:none;" data-single-submit="true">
        @csrf
        @method('PATCH')
        <div id="tire-bulk-hidden-ids"></div>
        <div class="bulk-edit-bar-inner">
            <span id="tire-bulk-count" class="bulk-edit-count"></span>
            <div class="field">
                <input type="text" class="input sm" name="brand" placeholder="{{ __('Marca') }}">
            </div>
            <div class="field">
                <input type="text" class="input sm" name="model_name" placeholder="{{ __('Modello') }}">
            </div>
            <x-form.tire-size-input name="size" label="{{ __('Misura') }}" />
            <button type="submit" class="btn primary sm">{{ __('Applica a selezionate') }}</button>
            <button type="button" id="tire-bulk-clear" class="btn ghost sm">{{ __('Annulla selezione') }}</button>
        </div>
        @error('brand')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @error('size')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </form>

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco pneumatici') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ $statusFilterUrl('mounted') }}"
                        class="chip {{ $statusFilter === 'mounted' ? 'on' : '' }}">{{ __('Montate') }}</a>
                    <a href="{{ $statusFilterUrl('stored') }}"
                        class="chip {{ $statusFilter === 'stored' ? 'on' : '' }}">{{ __('In magazzino') }}</a>
                    <a href="{{ $statusFilterUrl('retired') }}"
                        class="chip {{ $statusFilter === 'retired' ? 'on' : '' }}">{{ __('Dismesse') }}</a>
                    <a href="{{ $seasonDueUrl }}"
                        class="chip {{ $seasonFilter === 'due' ? 'on' : '' }}">{{ __('Da cambiare') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.tires.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca marca, misura o veicolo') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.tires.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <form action="{{ route('admin.tires.index') }}" method="GET">
                    @foreach (request()->except('vehicle_id', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select class="select sm" name="vehicle_id" onchange="this.form.submit()">
                        <option value="">{{ __('Tutti i veicoli') }}</option>
                        @foreach ($vehiclesForFilter as $vehicle)
                            <option value="{{ $vehicle->id }}" {{ (string) $vehicleId === (string) $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }}
                            </option>
                        @endforeach
                    </select>
                </form>
                <form action="{{ route('admin.tires.index') }}" method="GET">
                    @foreach (request()->except('season_filter', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select class="select sm" name="season_filter" onchange="this.form.submit()">
                        <option value="all" {{ $seasonFilter === 'all' ? 'selected' : '' }}>{{ __('Tutte le stagionalità') }}</option>
                        @foreach ($seasonLabels as $value => $label)
                            <option value="{{ $value }}" {{ $seasonFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.tires.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo set') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="tire-select-all" title="{{ __('Seleziona tutte (pagina corrente)') }}"></th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Stagionalità') }}</span>
                                <a href="{{ $sortToggleUrl('season') }}"
                                    class="mini {{ $sortBy === 'season' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stagionalità') }}">{{ $sortIcon('season') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Posizione') }}</th>
                        <th>{{ __('Marca / Modello') }}</th>
                        <th>{{ __('Misura') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Stato') }}</span>
                                <a href="{{ $sortToggleUrl('status') }}"
                                    class="mini {{ $sortBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stato') }}">{{ $sortIcon('status') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Prossimo cambio') }}</span>
                                <a href="{{ $sortToggleUrl('next_change') }}"
                                    class="mini {{ $sortBy === 'next_change' ? 'on' : '' }}"
                                    title="{{ __('Ordina per prossimo cambio') }}">{{ $sortIcon('next_change') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tires as $tire)
                        <tr>
                            <td><input type="checkbox" class="tire-select" value="{{ $tire->id }}"></td>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($tire->id % 5) + 1 }}"><i
                                            class="fa-solid fa-circle-dot"></i></span>
                                    <div class="vin-name">{{ $tire->vehicle?->internal_code ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td>{{ $tire->season_label }}</td>
                            <td>{{ $tire->position_label }}</td>
                            <td>{{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: '—' }}</td>
                            <td class="code">{{ $tire->size ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $badgeClass($tire->status) }}">{{ $tire->status_label }}</span>
                            </td>
                            <td>
                                {{ $tire->next_change_date_formatted ?? '—' }}
                                @if ($tire->change_due)
                                    <div class="cell-sub">{{ __('cambio consigliato') }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.tires.show', $tire->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.tires.edit', $tire->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $tire->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="tire" :object="$tire" />
                    @empty
                        <tr>
                            <td colspan="9" class="empty">{{ __('Nessun set di gomme trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tires->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $tires->currentPage() }} / {{ $tires->lastPage() }} ·
                    {{ $tires->total() }}</span>
                <nav>
                    {{ $tires->links() }}
                </nav>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('tire-select-all');
                const checkboxes = () => Array.from(document.querySelectorAll('.tire-select'));
                const bar = document.getElementById('tire-bulk-form');
                const countLabel = document.getElementById('tire-bulk-count');
                const hiddenContainer = document.getElementById('tire-bulk-hidden-ids');
                const clearBtn = document.getElementById('tire-bulk-clear');

                const updateBar = () => {
                    const selected = checkboxes().filter(c => c.checked);
                    if (selected.length > 0) {
                        bar.style.display = '';
                        countLabel.textContent = selected.length === 1
                            ? '{{ __('1 pneumatico selezionato') }}'
                            : selected.length + ' {{ __('pneumatici selezionati') }}';
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
                        input.name = 'tire_ids[]';
                        input.value = c.value;
                        hiddenContainer.appendChild(input);
                    });
                });
            });
        </script>
    @endpush
@endsection
