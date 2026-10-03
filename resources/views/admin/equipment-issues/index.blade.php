@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Guasti Attrezzature')],
    ]" />
@endsection

@section('content')
    @php
        $badgeClass = fn($color) => match ($color) {
            'red' => 'b-red',
            'yellow' => 'b-amber',
            'green' => 'b-green',
            default => 'b-gray',
        };
        $statusFilterUrl = fn($status) => route('admin.equipment-issues.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco guasti attrezzature') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ $statusFilterUrl('open') }}"
                        class="chip {{ $statusFilter === 'open' ? 'on' : '' }}">{{ __('Aperti') }}</a>
                    <a href="{{ $statusFilterUrl('in_progress') }}"
                        class="chip {{ $statusFilter === 'in_progress' ? 'on' : '' }}">{{ __('In lavorazione') }}</a>
                    <a href="{{ $statusFilterUrl('closed') }}"
                        class="chip {{ $statusFilter === 'closed' ? 'on' : '' }}">{{ __('Risolti') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.equipment-issues.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca attrezzatura o descrizione') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.equipment-issues.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.equipment-issues.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo guasto') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Attrezzatura') }}</span>
                                <a href="{{ $groupToggleUrl('equipment') }}"
                                    class="mini {{ $groupBy === 'equipment' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per attrezzatura') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('equipment') }}"
                                    class="mini {{ $sortBy === 'equipment' ? 'on' : '' }}"
                                    title="{{ __('Ordina per attrezzatura') }}">{{ $sortIcon('equipment') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Descrizione') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Stato') }}</span>
                                <a href="{{ $groupToggleUrl('status') }}"
                                    class="mini {{ $groupBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per stato') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('status') }}"
                                    class="mini {{ $sortBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stato') }}">{{ $sortIcon('status') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Data') }}</span>
                                <a href="{{ $groupToggleUrl('date') }}"
                                    class="mini {{ $groupBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per data') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('date') }}"
                                    class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $groups = $groupBy !== null ? $groupedIssues : collect([__('Tutti i guasti') => $issues]);
                    @endphp

                    @forelse ($groups as $groupLabel => $groupIssues)
                        @if ($groupBy !== null)
                            <tr class="group-row" data-group-row="g{{ $loop->index }}">
                                <td colspan="5"><i class="fa-solid fa-chevron-down group-chevron"></i>{{ $groupLabel }} ({{ $groupIssues->count() }})</td>
                            </tr>
                        @endif

                        @foreach ($groupIssues as $issue)
                            <tr @if ($groupBy !== null) data-groups="g{{ $loop->parent->index }}" @endif>
                                <td>
                                    <div class="vin">
                                        <span class="thumb t{{ ($issue->equipment_id % 5) + 1 }}"><i
                                                class="fa-solid fa-toolbox"></i></span>
                                        <div>
                                            <div class="vin-name">{{ $issue->equipment->name ?: ($issue->equipment->equipmentType->name ?? 'N/A') }}</div>
                                            <div class="vin-sub">{{ $issue->equipment->vehicle?->internal_code ?? __('Non assegnata') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $issue->description }}</td>
                                <td><span
                                        class="badge {{ $badgeClass($issue->status_color) }}">{{ $issue->status_label }}</span>
                                </td>
                                <td>{{ $issue->event_date_formatted ?? 'N/A' }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.equipment-issues.show', $issue->id) }}" class="mini-btn"
                                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.equipment-issues.edit', $issue->id) }}" class="mini-btn"
                                            title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                        <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                            data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <x-admin.delete-modal type="equipmentissue" :object="$issue" />
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessun guasto trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
