@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Officine')],
    ]" />
@endsection

@section('content')
    @php
        $typeBadge = fn($type) => match ($type) {
            'Meccanico' => 'b-blue',
            'Elettrauto' => 'b-green',
            'Gommista' => 'b-purple',
            'Lavaggio' => 'b-blue',
            'Allestitore' => 'b-purple',
            'Vetri' => 'b-green',
            'Carrozziere' => 'b-gray',
            'Centro Revisioni' => 'b-amber',
            default => 'b-gray',
        };
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco officine') }}</h2>
            </div>
            <div class="filters">
                <form action="{{ route('admin.providers.index') }}" method="GET" class="search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca nome o indirizzo') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.providers.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.csv.export', 'providers') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.providers.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova officina') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Contatti') }}</th>
                        <th>{{ __('Indirizzo') }}</th>
                        <th>{{ __('Tipo') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($provider->id % 5) + 1 }}"><i
                                            class="fa-solid fa-wrench"></i></span>
                                    <div class="vin-name">{{ $provider->name }}</div>
                                </div>
                            </td>
                            <td>
                                @php($contactParts = array_filter(array_map('trim', explode('·', $provider->contact_info ?? ''))))
                                @if (count($contactParts) > 1)
                                    {{ $contactParts[0] }}
                                    <div class="cell-sub">{{ implode(' · ', array_slice($contactParts, 1)) }}</div>
                                @else
                                    {{ $provider->contact_info ?: '—' }}
                                @endif
                            </td>
                            <td>{{ $provider->address ?: '—' }}</td>
                            <td>
                                @if ($provider->type)
                                    <span class="badge {{ $typeBadge($provider->type) }}">{{ $provider->type }}</span>
                                @else
                                    <span class="badge b-gray">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.providers.show', $provider->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.providers.edit', $provider->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $provider->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="provider" :object="$provider" />
                    @empty
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessuna officina trovata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($providers->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $providers->currentPage() }} / {{ $providers->lastPage() }} ·
                    {{ $providers->total() }}</span>
                <nav>
                    {{ $providers->links() }}
                </nav>
            </div>
        @endif
    </div>
@endsection
