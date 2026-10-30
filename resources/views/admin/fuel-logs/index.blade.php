@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Carburante')],
    ]" />
@endsection

@section('content')

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco rifornimenti') }}</h2>
            </div>
            <div class="filters">
                <a href="{{ route('admin.csv.export', 'fuel-logs') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                {{-- Nascosto <768px: duplica il FAB "+" già nella bottom nav
                (restyle "Saru", ADR §19). --}}
                <a href="{{ route('admin.fuel-logs.create') }}" class="btn primary page-header-quick-add">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo rifornimento') }}
                </a>
            </div>
        </div>

        <div class="table-responsive table-desktop-only">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Sigla') }}</span>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Targa') }}</th>
                        <th>{{ __('Alimentazione') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Data') }}</span>
                                <a href="{{ $sortToggleUrl('date') }}" class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Litri') }}</span>
                                <a href="{{ $sortToggleUrl('liters') }}"
                                    class="mini {{ $sortBy === 'liters' ? 'on' : '' }}"
                                    title="{{ __('Ordina per litri') }}">{{ $sortIcon('liters') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Costo') }}</th>
                        <th>{{ __('Consumo') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fuelLogs as $fuelLog)
                        <tr>
                            <td class="code">{{ $fuelLog->vehicle->internal_code ?? 'N/A' }}</td>
                            <td class="code">{{ $fuelLog->vehicle->license_plate ?? 'N/A' }}</td>
                            <td>
                                @if ($fuelLog->vehicle?->fuel_type)
                                    <x-admin.status-badge
                                        :color="\App\Models\Vehicle::fuelTypeBadgeColors()[$fuelLog->vehicle->fuel_type] ?? 'gray'">
                                        {{ $fuelLog->vehicle->fuel_type_label }}
                                    </x-admin.status-badge>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $fuelLog->log_date_formatted ?? 'N/A' }}</td>
                            <td>{{ number_format($fuelLog->liters, 2, ',', '.') }} L</td>
                            <td>{{ $fuelLog->cost_total ? '€ '.number_format($fuelLog->cost_total, 2, ',', '.') : 'N/A' }}</td>
                            <td>
                                @php($consumption = $consumptionByLogId[$fuelLog->id] ?? null)
                                {{ $consumption !== null ? number_format($consumption, 2, ',', '.').' km/L' : 'N/A' }}
                            </td>
                            <td>
                                <x-admin.row-actions
                                    :show-url="route('admin.fuel-logs.show', $fuelLog->id)"
                                    :edit-url="route('admin.fuel-logs.edit', $fuelLog->id)"
                                    :delete-target="'#confirmDeleteModal-' . $fuelLog->id" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty">{{ __('Nessun rifornimento registrato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Lista a card <768px al posto della tabella con scroll
        orizzontale (restyle "Saru", ADR §19): stessi dati di `$fuelLogs`. --}}
        <div class="card-list-mobile">
            @forelse ($fuelLogs as $fuelLog)
                <a href="{{ route('admin.fuel-logs.show', $fuelLog->id) }}" class="card-list-item">
                    <span class="thumb t{{ ($fuelLog->vehicle_id % 5) + 1 }}"><i
                            class="fa-solid fa-gas-pump"></i></span>
                    <div class="card-list-item-body">
                        <div class="card-list-item-title">{{ $fuelLog->vehicle->internal_code ?? 'N/A' }}</div>
                        <div class="card-list-item-sub">{{ $fuelLog->log_date_formatted ?? 'N/A' }} ·
                            {{ number_format($fuelLog->liters, 2, ',', '.') }} L</div>
                    </div>
                    <div class="card-list-item-side">
                        <div class="card-list-item-side-label">{{ __('Costo') }}</div>
                        <div>{{ $fuelLog->cost_total ? '€ '.number_format($fuelLog->cost_total, 2, ',', '.') : 'N/A' }}
                        </div>
                    </div>
                </a>
            @empty
                <p class="empty">{{ __('Nessun rifornimento registrato.') }}</p>
            @endforelse
        </div>
    </div>

    @foreach ($fuelLogs as $fuelLog)
        <x-admin.delete-modal type="fuelLog" :object="$fuelLog" />
    @endforeach
@endsection
