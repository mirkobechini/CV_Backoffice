@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Chilometraggi" tableClass="table table-striped table-hover my-0 align-middle text-center"
        :csvRoute="route('admin.csv.export', 'mileage-logs')" :paginator="$mileageLogs">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.mileage-logs.create')" label="chilometraggio" />
            <a href="{{ route('admin.mileage-logs.bulk') }}" class="btn btn-outline-primary btn-sm">Rilevazione mensile</a>
            <a href="{{ route('admin.mileage-logs.pivot') }}" class="btn btn-outline-primary btn-sm">Vista mensile</a>
            <a href="{{ route('admin.csv-import.index') }}" class="btn btn-outline-primary btn-sm">Importa CSV</a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Sigla</th>
            <th scope="col">Targa</th>
            <th scope="col">Data</th>
            <th scope="col">Km</th>
            <th scope="col">Elimina</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($mileageLogs as $mileageLog)
                <tr>
                    <td>{{ $mileageLog->vehicle->internal_code }}</td>
                    <td>{{ $mileageLog->vehicle->license_plate }}</td>
                    <td>{{ $mileageLog->log_date_formatted ?? $mileageLog->log_date }}</td>
                    <td>{{ number_format($mileageLog->mileage, 0, ',', '.') }}</td>
                    <td>
                        <button type="button" data-bs-toggle="modal"
                            data-bs-target="#confirmDeleteModal-{{ $mileageLog->id }}" class="btn btn-danger btn-sm"
                            aria-label="Elimina chilometraggio">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <x-admin.delete-modal type="mileageLog" :object="$mileageLog" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
