@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Officine">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.providers.create')" label="officina" />
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
                    <x-admin.row-actions :showUrl="route('admin.providers.show', $provider->id)" :editUrl="route('admin.providers.edit', $provider->id)" :deleteTarget="'#confirmDeleteModal-' . $provider->id" :label="'officina ' . $provider->name" />
                </tr>
                <x-delete-modal type="provider" :object="$provider" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
