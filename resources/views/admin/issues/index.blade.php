@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Guasti">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.issues.create')" label="guasto" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">Veicolo</th>
            <th scope="col">Descrizione</th>
            <th scope="col">Stato</th>
            <th scope="col">Data</th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @foreach ($issues as $issue)
                <tr>
                    <td>{{ $issue->vehicle->internal_code }}</td>
                    <td>{{ $issue->description }}</td>
                    <td>
                        @switch($issue->status)
                            @case('open')
                                <span class="badge bg-danger">Aperto</span>
                            @break

                            @case('in_progress')
                                <span class="badge bg-warning text-dark">In lavorazione</span>
                            @break

                            @case('closed')
                                <span class="badge bg-success">Risolto</span>
                            @break

                            @default
                                <span class="badge bg-secondary">Sconosciuto</span>
                        @endswitch
                    </td>
                    <td>{{ $issue->event_date_formatted }}</td>
                    <x-admin.row-actions :showUrl="route('admin.issues.show', $issue->id)" :editUrl="route('admin.issues.edit', $issue->id)" :deleteTarget="'#confirmDeleteModal-' . $issue->id" :label="'guasto ' . $issue->description" />
                </tr>
                <x-admin.delete-modal type="issue" :object="$issue" />
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
