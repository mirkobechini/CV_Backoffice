@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Chilometraggi" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.mileagelogs.create')" label="chilometraggio" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Sigla</th>
            <th scope="col">Targa</th>
            <th scope="col">Km</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($mileageLogs as $mileageLog)
                <tr>
                    <td>{{ $mileageLog->vehicle->internal_code }}</td>
                    <td>{{ $mileageLog->vehicle->license_plate }}</td>
                    <td>{{ $mileageLog->mileage }}</td>
                    <x-admin.row-actions :showUrl="route('admin.mileagelogs.show', $mileageLog->id)" :editUrl="route('admin.mileagelogs.edit', $mileageLog->id)" :deleteTarget="'#confirmDeleteModal-' . $mileageLog->id" :label="'chilometraggio ' . $mileageLog->id" />
                </tr>
                <x-admin.delete-modal type="mileageLog" :object="$mileageLog" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
