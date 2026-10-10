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

    <div class="table-card" style="margin-top:20px;">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Stato documenti — Attrezzature') }}</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Attrezzatura') }}</th>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Revisione') }}</th>
                        <th>{{ __('Collaudo') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($equipment->isEmpty())
                        <tr>
                            <td colspan="4" class="empty">{{ __('Nessuna attrezzatura trovata.') }}</td>
                        </tr>
                    @else
                        @foreach ($equipment as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.equipments.show', $item) }}">
                                        {{ $item->name ?? $item->equipmentType?->name ?? 'N/A' }}
                                    </a>
                                </td>
                                <td class="code">{{ $item->vehicle?->internal_code ?? 'N/A' }}</td>
                                <td><span class="badge {{ $badgeClass($item->status_color) }}">{{ $item->status_label }}</span></td>
                                <td><span class="badge {{ $badgeClass($item->collaudo_status_color) }}">{{ $item->collaudo_status_label }}</span></td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
