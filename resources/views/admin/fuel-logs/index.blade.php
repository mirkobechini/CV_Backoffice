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
                <a href="{{ route('admin.fuel-logs.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo rifornimento') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
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
                            <td>{{ $fuelLog->log_date_formatted ?? 'N/A' }}</td>
                            <td>{{ number_format($fuelLog->liters, 2, ',', '.') }} L</td>
                            <td>{{ $fuelLog->cost_total ? '€ '.number_format($fuelLog->cost_total, 2, ',', '.') : 'N/A' }}</td>
                            <td>
                                @php($consumption = $consumptionByLogId[$fuelLog->id] ?? null)
                                {{ $consumption !== null ? number_format($consumption, 2, ',', '.').' km/L' : 'N/A' }}
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="{{ route('admin.fuel-logs.show', $fuelLog->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}" aria-label="{{ __('Visualizza') }}"><i
                                            class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.fuel-logs.edit', $fuelLog->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}" aria-label="{{ __('Modifica') }}"><i
                                            class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        aria-label="{{ __('Elimina') }}" data-bs-toggle="modal"
                                        data-bs-target="#confirmDeleteModal-{{ $fuelLog->id }}">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty">{{ __('Nessun rifornimento registrato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($fuelLogs as $fuelLog)
        <x-admin.delete-modal type="fuelLog" :object="$fuelLog" />
    @endforeach
@endsection
