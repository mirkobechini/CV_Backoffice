@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Guasti">
        <x-slot:headingActions>
            <a class="btn btn-success rounded-pill py-0 px-2" href="{{ route('admin.issues.create') }}">
                <i class="fa-solid fa-add text-light"></i>
            </a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Veicolo</th>
            <th scope="col">Descrizione</th>
            <th scope="col">Stato</th>
            <th scope="col">Data</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($issues as $issue)
                <tr>
                    <td>{{ $issue->vehicle->internal_code }}</td>
                    <td>{{ $issue->description }}</td>
                    <td>{{ $issue->status }}</td>
                    <td>{{ $issue->event_date }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.issues.show', $issue->id) }}" class="btn btn-primary"
                            aria-label="Visualizza guasto {{ $issue->description }}">Visualizza guasto</a>
                        <a href="{{ route('admin.issues.edit', $issue->id) }}" class="btn btn-warning"
                            aria-label="Modifica guasto {{ $issue->description }}">Modifica</a>
                        <button type="button" data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal-{{ $issue->id }}" class="btn btn-danger"
                            aria-label="Elimina guasto {{ $issue->description }}">Elimina</button>
                    </td>
                </tr>
                <x-delete-modal type="issue" :object="$issue" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
