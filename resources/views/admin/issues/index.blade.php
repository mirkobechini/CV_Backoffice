@extends('layouts.app')
@section('content')
    @php
        $groupBy = $groupBy ?? null;
        $sortBy = $sortBy ?? null;
        $sortDir = $sortDir ?? 'asc';

        $groupToggleUrl = function (string $field) use ($groupBy) {
            $query = request()->query();

            if ($groupBy === $field) {
                unset($query['group_by']);
            } else {
                $query['group_by'] = $field;
            }

            return route('admin.issues.index', $query);
        };

        $sortToggleUrl = function (string $field) use ($sortBy, $sortDir) {
            $query = request()->query();
            $nextDirection = $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc';

            $query['sort_by'] = $field;
            $query['sort_dir'] = $nextDirection;

            return route('admin.issues.index', $query);
        };

        $sortIcon = function (string $field) use ($sortBy, $sortDir) {
            if ($sortBy !== $field) {
                return '↕';
            }

            return $sortDir === 'asc' ? '↑' : '↓';
        };
    @endphp

    <x-admin.index-table title="Guasti">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.issues.create')" label="guasto" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Veicolo</span>
                    <a href="{{ $groupToggleUrl('vehicle') }}"
                        class="btn btn-sm {{ $groupBy === 'vehicle' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per veicolo">Grp</a>
                    <a href="{{ $sortToggleUrl('vehicle') }}"
                        class="btn btn-sm {{ $sortBy === 'vehicle' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per veicolo">{{ $sortIcon('vehicle') }}</a>
                </div>
            </th>
            <th scope="col">Descrizione</th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Stato</span>
                    <a href="{{ $groupToggleUrl('status') }}"
                        class="btn btn-sm {{ $groupBy === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per stato">Grp</a>
                    <a href="{{ $sortToggleUrl('status') }}"
                        class="btn btn-sm {{ $sortBy === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per stato">{{ $sortIcon('status') }}</a>
                </div>
            </th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Data</span>
                    <a href="{{ $groupToggleUrl('date') }}"
                        class="btn btn-sm {{ $groupBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per data">Grp</a>
                    <a href="{{ $sortToggleUrl('date') }}"
                        class="btn btn-sm {{ $sortBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per data">{{ $sortIcon('date') }}</a>
                </div>
            </th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @php
                $groups = $groupBy !== null ? $groupedIssues : collect(['Tutti i guasti' => $issues]);
            @endphp

            @foreach ($groups as $groupLabel => $groupIssues)
                @if ($groupBy !== null)
                    <tr class="table-light">
                        <td colspan="5"><strong>{{ $groupLabel }}</strong> ({{ $groupIssues->count() }})</td>
                    </tr>
                @endif

                @foreach ($groupIssues as $issue)
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
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
