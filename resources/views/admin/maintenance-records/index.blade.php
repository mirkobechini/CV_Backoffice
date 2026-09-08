@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Manutenzioni" :csvRoute="route('admin.csv.export', 'maintenance-records')">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.maintenance-records.create')" label="manutenzione" />
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
                        <td>{{ $record->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable?->description ?? ($record->activity_type ?? 'N/A') }}
                        </td>
                        <td>{{ $record->appointment_date_formatted ?? 'N/A' }}</td>
                        <x-admin.row-actions :showUrl="route('admin.maintenance-records.show', $record->id)" :editUrl="route('admin.maintenance-records.edit', $record->id)" :deleteTarget="'#confirmDeleteModal-' . $record->id" :label="'manutenzione ' .
                            ($record->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable
                                ?->description ??
                                ($record->activity_type ?? $record->id))" />
                    </tr>
                    <x-admin.delete-modal type="maintenanceRecord" :object="$record" />
                @endforeach
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
@endsection
