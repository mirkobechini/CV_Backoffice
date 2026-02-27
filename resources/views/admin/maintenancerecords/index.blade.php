@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1 class="mb-4">Manutenzioni</h1>
        <div class="card my-0">

            <table class="table table-striped table-hover my-0">
                <thead>
                    <tr>
                        <th scope="col">Veicolo</th>
                        <th scope="col">Descrizione</th>
                        <th scope="col">Data</th>
                        <th scope="col">Azioni</th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
        </div>
    </div>
@endsection