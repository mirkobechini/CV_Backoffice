@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Officine">
        <x-slot:headingActions>
            <a class="btn btn-success rounded-pill py-0 px-2" href="{{ route('admin.providers.create') }}">
                <i class="fa-solid fa-add text-light"></i>
            </a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Nome</th>
            <th scope="col">Contatti</th>
            <th scope="col">Indirizzo</th>
            <th scope="col">Tipo</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($providers as $provider)
                <tr>
                    <td>{{ $provider->name }}</td>
                    <td>{{ $provider->contact_info }}</td>
                    <td>{{ $provider->address }}</td>
                    <td>{{ $provider->type }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.providers.show', $provider->id) }}" class="btn btn-primary"
                            aria-label="Visualizza officina {{ $provider->name }}">Visualizza officina</a>
                        <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-warning"
                            aria-label="Modifica officina {{ $provider->name }}">Modifica</a>
                        <button type="button" data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal-{{ $provider->id }}" class="btn btn-danger"
                            aria-label="Elimina officina {{ $provider->name }}">Elimina</button>
                    </td>
                </tr>
                <x-delete-modal type="provider" :object="$provider" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
