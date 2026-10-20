@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Dispositivi pedaggio')],
    ]" />
@endsection

@section('content')

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco dispositivi') }}</h2>
            </div>
            <div class="filters">
                <a href="{{ route('admin.csv.export', 'fleet-devices') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.fleet-devices.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo dispositivo') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Tipo') }}</span>
                                <a href="{{ $sortToggleUrl('type') }}" class="mini {{ $sortBy === 'type' ? 'on' : '' }}"
                                    title="{{ __('Ordina per tipo') }}">{{ $sortIcon('type') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Codice') }}</span>
                                <a href="{{ $sortToggleUrl('code') }}" class="mini {{ $sortBy === 'code' ? 'on' : '' }}"
                                    title="{{ __('Ordina per codice') }}">{{ $sortIcon('code') }}</a>
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
                    @forelse ($fleetDevices as $fleetDevice)
                        <tr>
                            <td>{{ $fleetDevice->type_label }}</td>
                            <td class="code">{{ $fleetDevice->code }}</td>
                            <td>
                                @if ($fleetDevice->vehicle)
                                    {{ $fleetDevice->vehicle->internal_code }}
                                    <span class="cell-sub">{{ $fleetDevice->vehicle->license_plate }}</span>
                                @else
                                    <span class="hint">{{ __('Non assegnato') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.fleet-devices.show', $fleetDevice->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}" aria-label="{{ __('Visualizza') }}"><i
                                            class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.fleet-devices.edit', $fleetDevice->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}" aria-label="{{ __('Modifica') }}"><i
                                            class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        aria-label="{{ __('Elimina') }}" data-bs-toggle="modal"
                                        data-bs-target="#confirmDeleteModal-{{ $fleetDevice->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">{{ __('Nessun dispositivo registrato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($fleetDevices as $fleetDevice)
        <x-admin.delete-modal type="fleetDevice" :object="$fleetDevice" />
    @endforeach
@endsection
