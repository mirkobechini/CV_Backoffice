@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Manutenzioni">
        <x-slot:headingActions>
            <a class="btn btn-success rounded-pill py-0 px-2" href="{{ route('admin.maintenancerecords.create') }}">
                <i class="fa-solid fa-add text-light"></i>
            </a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Veicolo</th>
            <th scope="col">Descrizione</th>
            <th scope="col">Data</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($maintenanceRecords as $record)
                <tr>
                    <td>{{ $record->vehicle->internal_code }}</td>
                    <td>{{ $record->description }}</td>
                    <td>{{ $record->maintenance_date }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.maintenancerecords.show', $record->id) }}" class="btn btn-primary"
                            aria-label="Visualizza manutenzione {{ $record->description }}">Visualizza manutenzione</a>
                        <a href="{{ route('admin.maintenancerecords.edit', $record->id) }}" class="btn btn-warning"
                            aria-label="Modifica manutenzione {{ $record->description }}">Modifica</a>
                        <button type="button" data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal-{{ $record->id }}" class="btn btn-danger"
                            aria-label="Elimina manutenzione {{ $record->description }}">Elimina</button>
                    </td>
                </tr>
                <x-delete-modal type="maintenancerecord" :object="$record" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
