@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tipi di veicoli')],
    ]" />
@endsection

@section('content')
    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco tipi') }}</h2>
            </div>
            <div class="filters">
                <form action="{{ route('admin.vehicle-types.index') }}" method="GET" class="search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca tipo') }}" value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.vehicle-types.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.vehicle-types.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo tipo') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Revisione Ossigeno') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicleTypes as $vehicleType)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($loop->index % 5) + 1 }}"><i
                                            class="fa-solid fa-truck"></i></span>
                                    <div class="vin-name">{{ $vehicleType->name }}</div>
                                </div>
                            </td>
                            <td>
                                @if ($vehicleType->needs_oxygen_check)
                                    <span class="badge b-green"><i class="fa-solid fa-check"></i>
                                        {{ __('Richiesta') }}</span>
                                @else
                                    <span class="badge b-gray"><i class="fa-solid fa-xmark"></i>
                                        {{ __('Non richiesta') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.vehicle-types.show', $vehicleType->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.vehicle-types.edit', $vehicleType->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#confirmDeleteModal-{{ $vehicleType->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="vehicleType" :object="$vehicleType" />
                    @empty
                        <tr>
                            <td colspan="3" class="empty">{{ __('Nessun tipo di veicolo trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vehicleTypes->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $vehicleTypes->currentPage() }} / {{ $vehicleTypes->lastPage() }} ·
                    {{ $vehicleTypes->total() }}</span>
                <nav>
                    {{ $vehicleTypes->links() }}
                </nav>
            </div>
        @endif
    </div>
@endsection
