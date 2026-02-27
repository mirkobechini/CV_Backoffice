@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Manutenzioni">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.maintenancerecords.create')" label="manutenzione" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Veicolo</th>
            <th scope="col">Descrizione</th>
            <th scope="col">Data</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($maintenanceRecords as $record)
                <tr>
                    <td>{{ $record->vehicle->internal_code }}</td>
                    <td>{{ $record->description }}</td>
                    <td>{{ $record->maintenance_date }}</td>
                    <x-admin.row-actions :showUrl="route('admin.maintenancerecords.show', $record->id)" :editUrl="route('admin.maintenancerecords.edit', $record->id)" :deleteTarget="'#confirmDeleteModal-' . $record->id" :label="'manutenzione ' . $record->description" />
                </tr>
                <x-admin.delete-modal type="maintenancerecord" :object="$record" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
