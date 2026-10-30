@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Manutenzione')],
        ['label' => __('Scadenze')],
    ]" />
@endsection

@section('content')
    @php
        $latestRevisionToggleUrl = function () use ($latestRevisionOnly) {
            $query = request()->query();
            $query['latest_revision_only'] = $latestRevisionOnly ? '0' : '1';

            return route('admin.deadlines.index', $query);
        };
        $statusFilterUrl = fn($status) => route('admin.deadlines.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco scadenze') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutte') }}</a>
                    <a href="{{ $statusFilterUrl('expired') }}"
                        class="chip {{ $statusFilter === 'expired' ? 'on' : '' }}">{{ __('Scadute') }}</a>
                    <a href="{{ $statusFilterUrl('pending') }}"
                        class="chip {{ $statusFilter === 'pending' ? 'on' : '' }}">{{ __('Imminenti') }}</a>
                    <a href="{{ $statusFilterUrl('valid') }}"
                        class="chip {{ $statusFilter === 'valid' ? 'on' : '' }}">{{ __('Valide') }}</a>
                    <a href="{{ $statusFilterUrl('renewed') }}"
                        class="chip {{ $statusFilter === 'renewed' ? 'on' : '' }}">{{ __('Rinnovate') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.deadlines.index') }}" method="GET" class="filters"
                    style="align-items:center;">
                    @foreach (request()->except('q', 'page', 'type_filter') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <select name="type_filter" class="select" style="width:auto;" onchange="this.form.submit()">
                        <option value="all">{{ __('Tutte le tipologie') }}</option>
                        @foreach ($types as $type)
                            <option value="{{ $type }}" {{ $typeFilter === $type ? 'selected' : '' }}>
                                {{ $type }}
                            </option>
                        @endforeach
                    </select>
                    <div class="search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="q" placeholder="{{ __('cerca tipologia o veicolo') }}"
                            value="{{ request('q') }}">
                        @if (request('q'))
                            <a href="{{ route('admin.deadlines.index') }}" class="clear-search"><i
                                    class="fa-solid fa-xmark"></i></a>
                        @endif
                    </div>
                </form>
                <a href="{{ route('admin.deadlines.insurances') }}" class="btn" title="{{ __('Vista dedicata assicurazioni') }}">
                    <i class="fa-solid fa-shield-halved"></i> {{ __('Assicurazioni') }}
                </a>
                <a href="{{ route('admin.csv.export', 'deadlines') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.deadlines.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova scadenza') }}
                </a>
            </div>
        </div>

        <div class="table-responsive table-desktop-only">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Tipologia') }}</span>
                                <a href="{{ $groupToggleUrl('type') }}"
                                    class="mini {{ in_array('type', $groupByKeys, true) ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per tipologia (combinabile con veicolo)') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('type') }}"
                                    class="mini {{ $sortBy === 'type' ? 'on' : '' }}"
                                    title="{{ __('Ordina per tipologia') }}">{{ $sortIcon('type') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Manca') }}</span>
                                <a href="{{ $sortToggleUrl('date') }}"
                                    class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Status') }}</span>
                                <a href="{{ $latestRevisionToggleUrl() }}"
                                    class="mini {{ $latestRevisionOnly ? 'on' : '' }}"
                                    title="{{ __("Mostra solo l'ultima revisione per veicolo") }}">{{ __('Ultima') }}</a>
                                <a href="{{ $sortToggleUrl('status') }}"
                                    class="mini {{ $sortBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stato') }}">{{ $sortIcon('status') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $groupToggleUrl('vehicle') }}"
                                    class="mini {{ in_array('vehicle', $groupByKeys, true) ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per veicolo (combinabile con tipologia)') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if ($deadlines->isEmpty())
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessuna scadenza trovata.') }}</td>
                        </tr>
                    @elseif (!empty($groupByKeys))
                        @include('admin.deadlines._group', ['groups' => $groupedDeadlines, 'depth' => 0])
                    @else
                        @foreach ($deadlines as $deadline)
                            @include('admin.deadlines._row', ['deadline' => $deadline])
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Lista a card <768px al posto della tabella con scroll
        orizzontale (restyle "Saru", ADR §19): stessi dati di `$deadlines`
        (non raggruppati — il raggruppamento resta una funzione desktop). --}}
        <div class="card-list-mobile">
            @forelse ($deadlines as $deadline)
                <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="card-list-item">
                    <span class="thumb t{{ ($deadline->vehicle_id % 5) + 1 }}"><i class="fa-solid fa-clock"></i></span>
                    <div class="card-list-item-body">
                        <div class="card-list-item-title"><span
                                class="dot-type leg-{{ $deadline->type_slug }}"></span> {{ $deadline->type }}</div>
                        <div class="card-list-item-sub">{{ $deadline->vehicle->internal_code ?? 'N/A' }}</div>
                        <div class="card-list-item-tag">
                            <x-admin.status-badge
                                :color="$deadline->status_color">{{ $deadline->status_label }}</x-admin.status-badge>
                        </div>
                    </div>
                    @if ($deadline->date_remaining_label || $deadline->km_remaining_label)
                        <div class="card-list-item-side">
                            <div class="card-list-item-side-label">{{ __('Manca') }}</div>
                            @if ($deadline->date_remaining_label)
                                <div>{{ $deadline->date_remaining_label }}</div>
                            @endif
                            @if ($deadline->km_remaining_label)
                                <div>{{ $deadline->km_remaining_label }}</div>
                            @endif
                        </div>
                    @endif
                </a>
            @empty
                <p class="empty">{{ __('Nessuna scadenza trovata.') }}</p>
            @endforelse
        </div>
    </div>
@endsection
