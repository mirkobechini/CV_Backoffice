@extends('layouts.app')
@section('content')
    <div class="container py-4">

        <h1 class="mb-4">Officine</h1>
        <div class="card my-0">

            <table class="table table-striped table-hover my-0">
                <thead>
                    <tr>
                        <th scope="col">Nome</th>
                        <th scope="col">Contatti</th>
                        <th scope="col">Indirizzo</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($providers as $provider)
                        <tr>
                            <td>{{ $provider->name }}</td>
                            <td>{{ $provider->contact_info }}</td>
                            <td>{{ $provider->address }}</td>
                            <td>{{ $provider->type }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('providers.show', $provider->id) }}" class="btn btn-primary"
                                    aria-label="Visualizza officina {{ $provider->name }}">Visualizza officina</a>
                                <a href="{{ route('providers.edit', $provider->id) }}" class="btn btn-warning"
                                    aria-label="Modifica officina {{ $provider->name }}">Modifica</a>
                                <button type="button" data-bs-toggle="modal"
                                    data-bs-target="#confirmDeleteModal-{{ $provider->id }}" class="btn btn-danger"
                                    aria-label="Elimina officina {{ $provider->name }}">Elimina</button>
                            </td>
                        </tr>
                        <x-delete-modal type="provider" :object="$provider" />
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
