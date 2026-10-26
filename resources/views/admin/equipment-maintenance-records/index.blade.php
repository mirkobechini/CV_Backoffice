@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Appuntamenti Attrezzature')],
    ]" />
@endsection

@section('content')
    @php
        $statusFilterUrl = fn($status) => route('admin.equipment-maintenance-records.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco appuntamenti attrezzature') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ $statusFilterUrl('scheduled') }}"
                        class="chip {{ $statusFilter === 'scheduled' ? 'on' : '' }}">{{ __('In programma') }}</a>
                    <a href="{{ $statusFilterUrl('completed') }}"
                        class="chip {{ $statusFilter === 'completed' ? 'on' : '' }}">{{ __('Completati') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.equipment-maintenance-records.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca attrezzatura o fornitore') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.equipment-maintenance-records.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.equipment-maintenance-records.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo appuntamento') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Attrezzature') }}</th>
                        <th>{{ __('Fornitore') }}</th>
                        <th>{{ __('Tipologia') }}</th>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Costo') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            <td>
                                {{ $record->equipments->map(fn($e) => $e->name ?: ($e->equipmentType->name ?? 'N/A'))->implode(', ') }}
                            </td>
                            <td>{{ $record->provider->name ?? 'N/A' }}</td>
                            <td>{{ $record->activity_type ?? '—' }}</td>
                            <td>{{ $record->appointment_date_formatted }}</td>
                            <td>{{ $record->cost !== null ? '€ ' . number_format((float) $record->cost, 2, ',', '.') : '—' }}</td>
                            <td>
                                @if ($record->return_date)
                                    <x-admin.status-badge color="green">{{ __('Completato') }}</x-admin.status-badge>
                                @else
                                    <x-admin.status-badge color="yellow">{{ __('In programma') }}</x-admin.status-badge>
                                @endif
                            </td>
                            <td>
                                <x-admin.row-actions
                                    :show-url="route('admin.equipment-maintenance-records.show', $record->id)"
                                    :edit-url="route('admin.equipment-maintenance-records.edit', $record->id)"
                                    :delete-target="'#confirmDeleteModal-' . $record->id" />
                            </td>
                        </tr>
                        <x-admin.delete-modal type="equipmentmaintenancerecord" :object="$record" />
                    @empty
                        <tr>
                            <td colspan="7" class="empty">{{ __('Nessun appuntamento trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($records->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $records->currentPage() }} / {{ $records->lastPage() }} ·
                    {{ $records->total() }}</span>
                <nav>
                    {{ $records->links() }}
                </nav>
            </div>
        @endif
    </div>
@endsection
