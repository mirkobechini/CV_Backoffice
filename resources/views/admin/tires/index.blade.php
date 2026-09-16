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
    @endphp

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
                <a href="{{ route('admin.tires.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo set') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Stagionalità') }}</th>
                        <th>{{ __('Marca / Modello') }}</th>
                        <th>{{ __('Misura') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Prossimo cambio') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tires as $tire)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($tire->id % 5) + 1 }}"><i
                                            class="fa-solid fa-circle-dot"></i></span>
                                    <div class="vin-name">{{ $tire->vehicle?->internal_code ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td>{{ $tire->season_label }}</td>
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
                            <td colspan="7" class="empty">{{ __('Nessun set di gomme trovato.') }}</td>
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
@endsection
