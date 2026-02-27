@extends('layouts.app')
@section('content')
    <div class="container py-4">

        <h1 class="mb-4">Guasti</h1>
        <div class="card my-0">

            <table class="table table-striped table-hover my-0">
                <thead>
                    <tr>
                        <th scope="col">Veicolo</th>
                        <th scope="col">Descrizione</th>
                        <th scope="col">Stato</th>
                        <th scope="col">Data</th>
                        <th scope="col">Azioni</th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
        </div>
    </div>
@endsection
