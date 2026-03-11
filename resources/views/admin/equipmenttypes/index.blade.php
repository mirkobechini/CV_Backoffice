@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Tipi di attrezzature"
        tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.equipmenttypes.create')" label="tipo di attrezzatura" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Nome</th>
            <th scope="col">Prima revisione</th>
            <th scope="col">Revisione regolare</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($equipmentTypes as $equipmentType)
                <tr>
                    <td>{{ $equipmentType->name ?? 'N/A' }}</td>
                    <td>{{ $equipmentType->first_inspection_months ? $equipmentType->first_inspection_months . ' mesi' : 'N/A' }}</td>
                    <td>{{ $equipmentType->regular_inspection_months ? $equipmentType->regular_inspection_months . ' mesi' : 'N/A' }}</td>
                    <x-admin.row-actions :showUrl="route('admin.equipmenttypes.show', $equipmentType->id)" :editUrl="route('admin.equipmenttypes.edit', $equipmentType->id)" :deleteTarget="'#confirmDeleteModal-' . $equipmentType->id" :label="'tipo di attrezzatura ' . $equipmentType->id" />
                </tr>
                <x-admin.delete-modal type="equipmentType" :object="$equipmentType" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
