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
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Numero Seriale') }}</th>
                        <th>{{ __('Data di revisione') }}</th>
                        <th>{{ __('Sigla') }}</th>
                        <th>{{ __('Targa') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipments as $equipment)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($loop->index % 5) + 1 }}"><i
                                            class="fa-solid fa-fire-extinguisher"></i></span>
                                    <div class="vin-name">{{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
                                    </div>
                                </div>
                            </td>
                            <td class="code">{{ $equipment->serial_number ?: '—' }}</td>
                            <td>
                                {{ $equipment->revision_date_formatted ?? '—' }}
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
                            <td colspan="6" class="empty">{{ __('Nessuna attrezzatura trovata.') }}</td>
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
@endsection
