@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Tipi di veicoli" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.vehicle-types.create')" label="tipo di veicolo" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Nome</th>
            <th scope="col">Revisione Ossigeno</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($vehicleTypes as $vehicleType)
                <tr>
                    <td>{{ $vehicleType->name }}</td>
                    <td><i class="fa-solid {{ $vehicleType->needs_oxygen_check ? 'fa-check text-success' : 'fa-times text-danger' }}"></i></td>
                    <x-admin.row-actions :showUrl="route('admin.vehicle-types.show', $vehicleType->id)" :editUrl="route('admin.vehicle-types.edit', $vehicleType->id)" :deleteTarget="'#confirmDeleteModal-' . $vehicleType->id" :label="'tipo di veicolo ' . $vehicleType->name" />
                </tr>
                <x-admin.delete-modal type="vehicleType" :object="$vehicleType" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
