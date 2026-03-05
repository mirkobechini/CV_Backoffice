@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Attrezzature" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.equipments.create')" label="attrezzatura" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Sigla</th>
            <th scope="col">Targa</th>
            <th scope="col">Nome</th>
            <th scope="col">Data di revisione</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($equipments as $equipment)
                <tr>
                    <td>{{ $equipment->vehicle?->internal_code ?? 'N/A' }}</td>
                    <td>{{ $equipment->vehicle?->license_plate ?? 'N/A' }}</td>
                    <td>{{ $equipment->name }}</td>
                    <td>{{ $equipment->revision_date }}</td>
                    <x-admin.row-actions :showUrl="route('admin.equipments.show', $equipment->id)" :editUrl="route('admin.equipments.edit', $equipment->id)" :deleteTarget="'#confirmDeleteModal-' . $equipment->id" :label="'attrezzatura ' . $equipment->id" />
                </tr>
                <x-admin.delete-modal type="equipment" :object="$equipment" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
