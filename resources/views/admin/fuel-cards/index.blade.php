@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Tessere carburante')],
    ]" />
@endsection

@section('content')

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco tessere') }}</h2>
            </div>
            <div class="filters">
                <a href="{{ route('admin.csv.export', 'fuel-cards') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.fuel-cards.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova tessera') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Numero tessera') }}</span>
                                <a href="{{ $sortToggleUrl('card_number') }}"
                                    class="mini {{ $sortBy === 'card_number' ? 'on' : '' }}"
                                    title="{{ __('Ordina per numero') }}">{{ $sortIcon('card_number') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Fornitore') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Stato') }}</span>
                                <a href="{{ $sortToggleUrl('status') }}"
                                    class="mini {{ $sortBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stato') }}">{{ $sortIcon('status') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fuelCards as $fuelCard)
                        <tr>
                            <td class="code">{{ $fuelCard->card_number }}</td>
                            <td>{{ $fuelCard->provider ?? '—' }}</td>
                            <td>
                                <span
                                    class="badge {{ $fuelCard->status === 'assigned' ? 'b-green' : ($fuelCard->status === 'jolly' ? 'b-blue' : 'b-gray') }}">
                                    {{ $fuelCard->status_label }}
                                </span>
                            </td>
                            <td>
                                @if ($fuelCard->vehicle)
                                    {{ $fuelCard->vehicle->internal_code }}
                                    <span class="cell-sub">{{ $fuelCard->vehicle->license_plate }}</span>
                                @else
                                    <span class="hint">—</span>
                                @endif
                            </td>
                            <td>
                                <x-admin.row-actions
                                    :show-url="route('admin.fuel-cards.show', $fuelCard->id)"
                                    :edit-url="route('admin.fuel-cards.edit', $fuelCard->id)"
                                    :delete-target="'#confirmDeleteModal-' . $fuelCard->id" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessuna tessera registrata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($fuelCards as $fuelCard)
        <x-admin.delete-modal type="fuelCard" :object="$fuelCard" />
    @endforeach
@endsection
