@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Veicoli" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <a class="btn btn-success rounded-pill py-0 px-2" href="{{ route('admin.vehicles.create') }}">
                <i class="fa-solid fa-add text-light"></i>
            </a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Sigla</th>
            <th scope="col">Modello</th>
            <th scope="col">Targa</th>
            <th scope="col">Tipo</th>
            <th scope="col">Stato</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($vehicles as $vehicle)
                <tr>
                    <td>{{ $vehicle->internal_code }}</td>
                    <td>{{ $vehicle->model }}</td>
                    <td>
                        {{ preg_replace('/^([A-Z]{2})(\d{3})([A-Z]{2})$/', '$1 $2 $3', strtoupper($vehicle->license_plate)) }}
                    </td>
                    <td>{{ $vehicle->vehicleType->name ?? 'N/A' }}</td>
                    <td><i
                            class="fa-solid  {{ $vehicle->issues->isEmpty() ? 'fa-check text-success' : 'fa-exclamation-triangle text-danger' }}"></i>
                    </td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="btn btn-primary"
                            aria-label="Visualizza veicolo {{ $vehicle->internal_code }}">Visualizza veicolo</a>
                        <a href="{{ route('admin.vehicles.edit', $vehicle->id) }}" class="btn btn-warning"
                            aria-label="Modifica veicolo {{ $vehicle->internal_code }}">Modifica</a>
                        <button type="button" data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal-{{ $vehicle->id }}" class="btn btn-danger"
                            aria-label="Elimina veicolo {{ $vehicle->internal_code }}">Elimina</button>
                    </td>
                </tr>
                <x-delete-modal type="vehicle" :object="$vehicle" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
