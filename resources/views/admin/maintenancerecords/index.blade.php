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

            return route('admin.maintenancerecords.index', $query);
        };

        $sortToggleUrl = function (string $field) use ($sortBy, $sortDir) {
            $query = request()->query();
            $nextDirection = $sortBy === $field && $sortDir === 'asc' ? 'desc' : 'asc';

            $query['sort_by'] = $field;
            $query['sort_dir'] = $nextDirection;

            return route('admin.maintenancerecords.index', $query);
        };

        $sortIcon = function (string $field) use ($sortBy, $sortDir) {
            if ($sortBy !== $field) {
                return '↕';
            }

            return $sortDir === 'asc' ? '↑' : '↓';
        };
    @endphp

    <x-admin.index-table title="Manutenzioni">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.maintenancerecords.create')" label="manutenzione" />
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
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Descrizione</span>
                    <a href="{{ $groupToggleUrl('description') }}"
                        class="btn btn-sm {{ $groupBy === 'description' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per descrizione">Grp</a>
                    <a href="{{ $sortToggleUrl('description') }}"
                        class="btn btn-sm {{ $sortBy === 'description' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per descrizione">{{ $sortIcon('description') }}</a>
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
                $groups =
                    $groupBy !== null
                        ? $groupedMaintenanceRecords
                        : collect(['Tutti gli appuntamenti' => $maintenanceRecords]);
            @endphp

            @foreach ($groups as $groupLabel => $groupRecords)
                @if ($groupBy !== null)
                    <tr class="table-light">
                        <td colspan="4"><strong>{{ $groupLabel }}</strong> ({{ $groupRecords->count() }})</td>
                    </tr>
                @endif

                @foreach ($groupRecords as $record)
                    <tr>
                        <td>{{ $record->vehicle->internal_code }}</td>
                        <td>{{ $record->issue?->description ?? ($record->activity_type ?? 'N/A') }}</td>
                        <td>{{ $record->appointment_date_formatted ?? 'N/A' }}</td>
                        <x-admin.row-actions :showUrl="route('admin.maintenancerecords.show', $record->id)" :editUrl="route('admin.maintenancerecords.edit', $record->id)" :deleteTarget="'#confirmDeleteModal-' . $record->id" :label="'manutenzione ' .
                            ($record->issue?->description ?? ($record->activity_type ?? $record->id))" />
                    </tr>
                    <x-admin.delete-modal type="maintenanceRecord" :object="$record" />
                @endforeach
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
