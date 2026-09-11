@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
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
        $typeGroupToggleUrl = function () use ($groupBy) {
            $query = request()->query();
            $query['group_by'] = $groupBy === 'type' ? 'none' : 'type';

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
                <form action="{{ route('admin.deadlines.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca tipologia o veicolo') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.deadlines.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.csv.export', 'deadlines') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.deadlines.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova scadenza') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Tipologia') }}</span>
                                <a href="{{ $typeGroupToggleUrl() }}"
                                    class="mini {{ $groupBy === 'type' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per tipologia') }}">Grp</a>
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
                                    class="mini {{ $groupBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per veicolo') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $groups = $groupBy !== null ? $groupedDeadlines : collect([__('Tutte le scadenze') => $deadlines]);
                        $badgeClass = fn($color) => match ($color) {
                            'red' => 'b-red',
                            'yellow' => 'b-amber',
                            'green' => 'b-green',
                            default => 'b-gray',
                        };
                    @endphp

                    @forelse ($groups as $groupLabel => $groupDeadlines)
                        @if ($groupBy !== null)
                            <tr class="group-row">
                                <td colspan="5">{{ $groupLabel }} ({{ $groupDeadlines->count() }})</td>
                            </tr>
                        @endif

                        @foreach ($groupDeadlines as $deadline)
                            <tr>
                                <td>
                                    <div class="type-cell">
                                        <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                                        <span class="type-name">{{ $deadline->type }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="dl-badges">
                                        @if ($deadline->date_remaining_label)
                                            <span
                                                class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->date_remaining_label }}</span>
                                        @endif
                                        @if ($deadline->km_remaining_label)
                                            <span
                                                class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->km_remaining_label }}</span>
                                        @endif
                                        @if (!$deadline->date_remaining_label && !$deadline->km_remaining_label)
                                            <span class="badge b-gray">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td><span
                                        class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->status_label }}</span>
                                </td>
                                <td class="code">{{ $deadline->vehicle->internal_code ?? 'N/A' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.deadlines.edit', $deadline->id) }}" class="mini-btn"
                                            title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                        <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal-{{ $deadline->id }}"><i
                                                class="fa-solid fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <x-admin.delete-modal type="deadline" :object="$deadline" />
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessuna scadenza trovata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
