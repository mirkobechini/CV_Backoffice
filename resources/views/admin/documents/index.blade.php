@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Stato documenti')],
    ]" />
@endsection

@section('content')
    @php
        $badgeClass = fn ($color) => match ($color) {
            'red' => 'b-red',
            'yellow' => 'b-amber',
            'green' => 'b-green',
            default => 'b-gray',
        };
        $typeShortLabel = fn (string $type) => match ($type) {
            \App\Models\Deadline::TYPE_ASSICURAZIONE => __('Assicur.'),
            \App\Models\Deadline::TYPE_MINISTERIAL => __('Revis. min.'),
            \App\Models\Deadline::TYPE_OXYGEN => __('Revis. ossig.'),
            \App\Models\Deadline::TYPE_TAGLIANDO => __('Tagliando'),
            \App\Models\Deadline::TYPE_CINGHIA => __('Cinghia'),
            default => $type,
        };
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Stato documenti — Veicoli') }}</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Carta circolazione') }}</th>
                        <th>{{ __('Garanzia') }}</th>
                        @foreach ($deadlineTypes as $type)
                            <th>{{ $typeShortLabel($type) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($vehicles->isEmpty())
                        <tr>
                            <td colspan="{{ 3 + count($deadlineTypes) }}" class="empty">{{ __('Nessun veicolo trovato.') }}</td>
                        </tr>
                    @else
                        @foreach ($vehicles as $vehicle)
                            <tr>
                                <td class="code">
                                    <a href="{{ route('admin.vehicles.show', $vehicle) }}">
                                        {{ $vehicle->internal_code }} · {{ $vehicle->license_plate }}
                                    </a>
                                </td>
                                <td>
                                    @if ($vehicle->registration_card_path)
                                        <span class="badge b-green">{{ __('Presente') }}</span>
                                    @else
                                        <span class="badge b-red">{{ __('Mancante') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $vehicle->warranty_expiration_date)
                                        <span class="badge b-gray">N/A</span>
                                    @else
                                        <span class="badge {{ $vehicle->is_warranty_expired ? 'b-red' : 'b-green' }}">
                                            {{ $vehicle->warranty_expiration_date_formatted }}
                                        </span>
                                    @endif
                                </td>
                                @foreach ($deadlineTypes as $type)
                                    @php
                                        $deadline = $deadlinesByVehicle[$vehicle->id][$type] ?? null;
                                    @endphp
                                    <td>
                                        @if ($deadline)
                                            <a href="{{ route('admin.deadlines.show', $deadline) }}">
                                                <span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->status_label }}</span>
                                            </a>
                                        @else
                                            <span class="badge b-gray">N/A</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @php
        $equipmentStatusFilterUrl = fn ($status) => route('admin.documents.index', array_merge(request()->except('equipment_status_filter'), $status === 'all' ? [] : ['equipment_status_filter' => $status]));
        $equipmentGroupToggleUrl = fn ($field) => route('admin.documents.index', array_merge(request()->except('equipment_group_by'), $equipmentGroupBy === $field ? [] : ['equipment_group_by' => $field]));
    @endphp

    <div class="table-card" style="margin-top:20px;">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Stato documenti — Attrezzature') }}</h2>
                <div class="filters">
                    <a href="{{ $equipmentStatusFilterUrl('all') }}"
                        class="chip {{ $equipmentStatusFilter === 'all' ? 'on' : '' }}">{{ __('Tutte') }}</a>
                    <a href="{{ $equipmentStatusFilterUrl('pending') }}"
                        class="chip {{ $equipmentStatusFilter === 'pending' ? 'on' : '' }}">{{ __('In scadenza') }}</a>
                    <a href="{{ $equipmentStatusFilterUrl('expired') }}"
                        class="chip {{ $equipmentStatusFilter === 'expired' ? 'on' : '' }}">{{ __('Scadute') }}</a>
                    <a href="{{ $equipmentStatusFilterUrl('valid') }}"
                        class="chip {{ $equipmentStatusFilter === 'valid' ? 'on' : '' }}">{{ __('Valide') }}</a>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Attrezzatura') }}</span>
                                <a href="{{ $equipmentGroupToggleUrl('type') }}"
                                    class="mini {{ $equipmentGroupBy === 'type' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per tipo') }}">Grp</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $equipmentGroupToggleUrl('vehicle') }}"
                                    class="mini {{ $equipmentGroupBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per veicolo') }}">Grp</a>
                            </div>
                        </th>
                        <th>{{ __('Revisione') }}</th>
                        <th>{{ __('Collaudo') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($equipment->isEmpty())
                        <tr>
                            <td colspan="4" class="empty">{{ __('Nessuna attrezzatura trovata.') }}</td>
                        </tr>
                    @elseif ($groupedEquipment !== null)
                        @foreach ($groupedEquipment as $groupLabel => $groupItems)
                            <tr class="group-row" data-group-row="g{{ $loop->index }}">
                                <td colspan="4"><i class="fa-solid fa-chevron-down group-chevron"></i>{{ $groupLabel }} ({{ $groupItems->count() }})</td>
                            </tr>
                            @foreach ($groupItems as $item)
                                @include('admin.documents._equipment-row', ['item' => $item, 'groupKey' => $loop->parent->index])
                            @endforeach
                        @endforeach
                    @else
                        @foreach ($equipment as $item)
                            @include('admin.documents._equipment-row', ['item' => $item, 'groupKey' => null])
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
