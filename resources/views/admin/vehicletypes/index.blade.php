@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Tipi di veicoli" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.vehicletypes.create')" label="tipo di veicolo" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Nome</th>
            <th scope="col">Estintori</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($vehicleTypes as $vehicleType)
                <tr>
                    <td>{{ $vehicleType->name }}</td>
                    <td>{{ $vehicleType->extinguishers_required }}</td>
                    <x-admin.row-actions :showUrl="route('admin.vehicletypes.show', $vehicleType->id)" :editUrl="route('admin.vehicletypes.edit', $vehicleType->id)" :deleteTarget="'#confirmDeleteModal-' . $vehicleType->id" :label="'tipo di veicolo ' . $vehicleType->name" />
                </tr>
                <x-admin.delete-modal type="vehicleType" :object="$vehicleType" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
