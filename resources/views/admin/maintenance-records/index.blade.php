@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti')],
    ]" />
@endsection

@section('content')
    @php
        $badgeClass = fn($color) => match ($color) {
            'red' => 'b-red',
            'yellow' => 'b-amber',
            'green' => 'b-green',
            'blue' => 'b-blue',
            'purple' => 'b-purple',
            default => 'b-gray',
        };
        $activityBadge = fn($type) => match ($type) {
            \App\Models\MaintenanceRecord::ACTIVITY_TAGLIANDO => 'b-blue',
            'Riparazione' => 'b-red',
            \App\Models\MaintenanceRecord::ACTIVITY_REVISION_MINISTERIAL => 'b-blue',
            \App\Models\MaintenanceRecord::ACTIVITY_REVISION_OXYGEN => 'b-green',
            \App\Models\MaintenanceRecord::ACTIVITY_TIMING_BELT => 'b-purple',
            default => 'b-gray',
        };
        $statusFilterUrl = fn($status) => route('admin.maintenance-records.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
        $relativeDate = function ($date) {
            if (! $date) {
                return null;
            }
            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
            if ($days === 0) {
                return __('oggi');
            }
            return $days > 0 ? __('tra :n giorni', ['n' => $days]) : __(':n giorni fa', ['n' => abs($days)]);
        };
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco appuntamenti') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ $statusFilterUrl('scheduled') }}"
                        class="chip {{ $statusFilter === 'scheduled' ? 'on' : '' }}">{{ __('In programma') }}</a>
                    <a href="{{ $statusFilterUrl('completed') }}"
                        class="chip {{ $statusFilter === 'completed' ? 'on' : '' }}">{{ __('Completati') }}</a>
                    <a href="{{ $statusFilterUrl('with_issues') }}"
                        class="chip {{ $statusFilter === 'with_issues' ? 'on' : '' }}">{{ __('Con guasti') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.maintenance-records.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca veicolo o descrizione') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.maintenance-records.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.maintenance-records.calendar') }}" class="btn">
                    <i class="fa-solid fa-calendar"></i> {{ __('Calendario') }}
                </a>
                <a href="{{ route('admin.csv.export', 'maintenance-records') }}" class="btn"
                    title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.maintenance-records.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo appuntamento') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
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
                        <th>
                            <div class="th-wrap"><span>{{ __('Descrizione') }}</span>
                                <a href="{{ $groupToggleUrl('description') }}"
                                    class="mini {{ $groupBy === 'description' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per descrizione') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('description') }}"
                                    class="mini {{ $sortBy === 'description' ? 'on' : '' }}"
                                    title="{{ __('Ordina per descrizione') }}">{{ $sortIcon('description') }}</a>
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
                        $groups =
                            $groupBy !== null
                                ? $groupedMaintenanceRecords
                                : collect([__('Tutti gli appuntamenti') => $maintenanceRecords]);
                    @endphp

                    @forelse ($groups as $groupLabel => $groupRecords)
                        @if ($groupBy !== null)
                            <tr class="group-row">
                                <td colspan="4">{{ $groupLabel }} ({{ $groupRecords->count() }})</td>
                            </tr>
                        @endif

                        @foreach ($groupRecords as $record)
                            @php
                                $linkedIssues = $record->items
                                    ->where('itemable_type', \App\Models\Issue::class)
                                    ->map(fn($item) => $item->itemable)
                                    ->filter();
                                $issueDescriptions = $linkedIssues->pluck('description')->implode(', ');
                                $description = $issueDescriptions !== '' ? $issueDescriptions : $record->activity_type ?? __('N/D');
                            @endphp
                            <tr>
                                <td>
                                    <div class="vin">
                                        <span class="thumb t{{ ($loop->index % 5) + 1 }}"><i
                                                class="fa-solid fa-truck"></i></span>
                                        <div>
                                            <div class="vin-name">{{ $record->vehicle->brand->name ?? '' }}
                                                {{ $record->vehicle->carModel->name ?? 'N/A' }}</div>
                                            <div class="vin-sub">{{ $record->vehicle->internal_code ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="desc-cell">
                                        {{ $description }}
                                        @if ($record->activity_type)
                                            <span
                                                class="badge {{ $activityBadge($record->activity_type) }}">{{ $record->activity_type }}</span>
                                        @endif
                                        @if ($linkedIssues->isNotEmpty())
                                            <span class="badge b-red">{{ __('Guasto') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <span class="date-main">{{ $record->appointment_date_formatted ?? 'N/A' }}</span>
                                        @if ($record->appointment_date)
                                            <span
                                                class="date-sub">{{ $relativeDate($record->appointment_date) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.maintenance-records.show', $record->id) }}"
                                            class="mini-btn" title="{{ __('Visualizza') }}"><i
                                                class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.maintenance-records.edit', $record->id) }}"
                                            class="mini-btn" title="{{ __('Modifica') }}"><i
                                                class="fa-solid fa-pen"></i></a>
                                        <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal-{{ $record->id }}">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <x-admin.delete-modal type="maintenanceRecord" :object="$record" />
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="4" class="empty">{{ __('Nessun appuntamento trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
