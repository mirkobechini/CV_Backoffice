@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => __('Tipi Attrezzature')],
    ]" />
@endsection

@section('content')
    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco tipi') }}</h2>
            </div>
            <div class="filters">
                <form action="{{ route('admin.equipment-types.index') }}" method="GET" class="search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca tipo') }}" value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.equipment-types.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.equipment-types.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo tipo') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Prima revisione') }}</th>
                        <th>{{ __('Revisione regolare') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipmentTypes as $equipmentType)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($equipmentType->id % 5) + 1 }}"><i
                                            class="fa-solid fa-toolbox"></i></span>
                                    <div class="vin-name">{{ $equipmentType->name ?? 'N/A' }}</div>
                                </div>
                            </td>
                            <td>{{ $equipmentType->first_inspection_months_formatted ?? 'N/A' }}</td>
                            <td>{{ $equipmentType->regular_inspection_months_formatted ?? 'N/A' }}</td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.equipment-types.show', $equipmentType->id) }}"
                                        class="mini-btn" title="{{ __('Visualizza') }}"><i
                                            class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.equipment-types.edit', $equipmentType->id) }}"
                                        class="mini-btn" title="{{ __('Modifica') }}"><i
                                            class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#confirmDeleteModal-{{ $equipmentType->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="equipmentType" :object="$equipmentType" />
                    @empty
                        <tr>
                            <td colspan="4" class="empty">{{ __('Nessun tipo di attrezzatura trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($equipmentTypes->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $equipmentTypes->currentPage() }} / {{ $equipmentTypes->lastPage() }} ·
                    {{ $equipmentTypes->total() }}</span>
                <nav>
                    {{ $equipmentTypes->links() }}
                </nav>
            </div>
        @endif
    </div>
@endsection
