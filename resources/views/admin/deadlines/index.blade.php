@extends('layouts.app')
@section('content')
    @php
        $groupBy = $groupBy ?? null;
        $sortBy = $sortBy ?? null;
        $sortDir = $sortDir ?? 'asc';
        $latestRevisionOnly = $latestRevisionOnly ?? false;

        $groupToggleUrl = function (string $field) use ($groupBy) {
            $query = request()->query();

            if ($groupBy === $field) {
                unset($query['group_by']);
            } else {
                $query['group_by'] = $field;
            }

            return route('admin.deadlines.index', $query);
        };

        $sortToggleUrl = function (string $field) use ($sortBy, $sortDir) {
            $query = request()->query();
            $nextDirection = $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc';

            $query['sort_by'] = $field;
            $query['sort_dir'] = $nextDirection;

            return route('admin.deadlines.index', $query);
        };

        $sortIcon = function (string $field) use ($sortBy, $sortDir) {
            if ($sortBy !== $field) {
                return '↕';
            }

            return $sortDir === 'asc' ? '↑' : '↓';
        };

        $latestRevisionToggleUrl = function () use ($latestRevisionOnly) {
            $query = request()->query();

            if ($latestRevisionOnly) {
                unset($query['latest_revision_only']);
            } else {
                $query['latest_revision_only'] = '1';
            }

            return route('admin.deadlines.index', $query);
        };
    @endphp

    <x-admin.index-table title="Scadenze" tableClass="table table-striped table-hover my-0 align-middle text-center">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.deadlines.create')" label="scadenza" />
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Tipologia</span>
                    <a href="{{ $groupToggleUrl('type') }}"
                        class="btn btn-sm {{ $groupBy === 'type' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per tipologia">Grp</a>
                    <a href="{{ $sortToggleUrl('type') }}"
                        class="btn btn-sm {{ $sortBy === 'type' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per tipologia">{{ $sortIcon('type') }}</a>
                </div>
            </th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Data scadenza</span>
                    <a href="{{ $groupToggleUrl('date') }}"
                        class="btn btn-sm {{ $groupBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per data">Grp</a>
                    <a href="{{ $sortToggleUrl('date') }}"
                        class="btn btn-sm {{ $sortBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per data">{{ $sortIcon('date') }}</a>
                </div>
            </th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Status</span>
                    <a href="{{ $latestRevisionToggleUrl() }}"
                        class="btn btn-sm {{ $latestRevisionOnly ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Mostra solo l'ultima revisione per veicolo">Ultima</a>
                    <a href="{{ $sortToggleUrl('status') }}"
                        class="btn btn-sm {{ $sortBy === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per stato">{{ $sortIcon('status') }}</a>
                </div>
            </th>
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
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @php
                $groups = $groupBy !== null ? $groupedDeadlines : collect(['Tutte le scadenze' => $deadlines]);
            @endphp

            @foreach ($groups as $groupLabel => $groupDeadlines)
                @if ($groupBy !== null)
                    <tr class="table-light">
                        <td colspan="5"><strong>{{ $groupLabel }}</strong> ({{ $groupDeadlines->count() }})</td>
                    </tr>
                @endif

                @foreach ($groupDeadlines as $deadline)
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
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
