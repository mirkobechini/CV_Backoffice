@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Scadenze" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.deadlines.create')" label="scadenza" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Tipologia</th>
            <th scope="col">Data scadenza</th>
            <th scope="col">Status</th>
            <th scope="col">Veicolo</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($deadlines as $deadline)
                <tr>
                    <td>{{ $deadline->type }}</td>
                    <td>{{ $deadline->due_date_formatted ?? 'N/A' }}</td>
                    <td>
                        @switch($deadline->automatic_status)
                            @case('renewed')
                                <span class="badge bg-success">Rinnovata</span>
                            @break

                            @case('pending')
                                <span class="badge bg-warning text-dark">In scadenza</span>
                            @break

                            @case('expired')
                                <span class="badge bg-danger">Scaduta</span>
                            @break

                            @default
                                <span class="badge bg-secondary">Sconosciuto</span>
                        @endswitch
                    </td>
                    <td>{{ $deadline->vehicle->internal_code ?? 'N/A' }}</td>
                    <x-admin.row-actions :showUrl="route('admin.deadlines.show', $deadline->id)" :editUrl="route('admin.deadlines.edit', $deadline->id)" :deleteTarget="'#confirmDeleteModal-' . $deadline->id" :label="'scadenza ' . $deadline->type" />
                </tr>
                <x-admin.delete-modal type="deadline" :object="$deadline" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
