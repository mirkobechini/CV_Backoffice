@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Veicoli')],
    ]" />
@endsection

@section('content')

    {{-- Header: titolo + conteggio flotta + esportazioni/nuovo (restyle "Saru", ADR §19) --}}
    <div class="page-header">
        <div>
            <h1>{{ __('Veicoli') }}</h1>
            <div class="date">{{ trans_choice(':count veicolo nella flotta|:count veicoli nella flotta', $totalVehicles, ['count' => $totalVehicles]) }}
            </div>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="{{ route('admin.csv.export', 'vehicles') }}" class="btn" title="{{ __('Scarica CSV') }}">
                <i class="fa-solid fa-download"></i> CSV
            </a>
            <a href="{{ route('admin.vehicles.pdf.fleet-overview') }}" class="btn"
                title="{{ __('Scarica PDF riepilogo flotta') }}">
                <i class="fa-solid fa-file-pdf"></i> PDF
            </a>
            <a href="{{ route('admin.vehicles.create') }}" class="btn primary">
                <i class="fa-solid fa-plus"></i> {{ __('Nuovo veicolo') }}
            </a>
        </div>
    </div>

    {{-- Ricerca + filtri, affiancati e prominenti (mockup validato con Mirko) --}}
    <div style="display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap;">
        <form action="{{ route('admin.vehicles.index') }}" method="GET" class="search" style="flex:1; min-width:240px;">
            @foreach (request()->except('q', 'page') as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="{{ __('Cerca per sigla, targa, modello…') }}"
                value="{{ request('q') }}" style="width:100%;">
            @if (request('q'))
                <a href="{{ route('admin.vehicles.index') }}" class="clear-search"><i
                        class="fa-solid fa-xmark"></i></a>
            @endif
        </form>
        <div class="filters">
            @php($chipBase = request()->except(['filter', 'page']))
            <a href="{{ route('admin.vehicles.index', $chipBase) }}"
                class="chip {{ $filter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
            <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'available'])) }}"
                class="chip {{ $filter === 'available' ? 'on' : '' }}">{{ __('Disponibili') }}</a>
            <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'in_workshop'])) }}"
                class="chip {{ $filter === 'in_workshop' ? 'on' : '' }}">{{ __('In officina') }}</a>
            <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'deadline'])) }}"
                class="chip {{ $filter === 'deadline' ? 'on' : '' }}">{{ __('Con scadenze') }}</a>
            <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'issues'])) }}"
                class="chip {{ $filter === 'issues' ? 'on' : '' }}">{{ __('Guasti') }}</a>
            <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'incomplete'])) }}"
                class="chip {{ $filter === 'incomplete' ? 'on' : '' }}">{{ __('Da integrare') }}</a>
        </div>
    </div>

    <div class="table-card">
        <div class="legend">
            <span class="legend-item"><span class="dot-type leg-tagliando"></span>{{ __('Tagliando') }}</span>
            <span class="legend-item"><span class="dot-type leg-cinghia"></span>{{ __('Cinghia') }}</span>
            <span class="legend-item"><span class="dot-type leg-revisione"></span>{{ __('Revisione') }}</span>
            <span class="legend-item"><span class="dot-type leg-ossigeno"></span>{{ __('Ossigeno') }}</span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Codice') }}</th>
                        <th>{{ __('Km attuali') }}</th>
                        <th>{{ __('Prossima scadenza') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Guasti') }}</th>
                        <th>{{ __('Equip.') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td>
                                <div class="vin">
                                    <span class="thumb t{{ ($vehicle->id % 5) + 1 }}"><i
                                            class="fa-solid fa-truck"></i></span>
                                    <div>
                                        <div class="vin-name">{{ $vehicle->brand->name ?? '' }}
                                            {{ $vehicle->carModel->name ?? 'N/A' }}</div>
                                        <div class="vin-sub">{{ $vehicle->license_plate ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="code">{{ $vehicle->internal_code }}</td>
                            <td>
                                <div class="km-cell">{{ number_format($vehicle->mileage ?? 0, 0, ',', '.') }} km</div>
                                @if ($vehicle->previousMileageLog)
                                    <div class="km-sub">
                                        {{ number_format($vehicle->previousMileageLog->mileage, 0, ',', '.') }} ·
                                        {{ $vehicle->previousMileageLog->log_date?->translatedFormat('d M') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @php($activeDeadlines = $vehicle->deadlines->filter(fn($d) => in_array($d->automatic_status, ['pending', 'expired']))->take(2))
                                @if ($activeDeadlines->isEmpty())
                                    <span class="badge b-green">{{ __('Nessuna') }}</span>
                                @else
                                    <div class="deadline">
                                        @foreach ($activeDeadlines as $deadline)
                                            <div class="dl-row">
                                                <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                                                <span class="dl-type">{{ $deadline->type }}</span>
                                                <span
                                                    class="count c-{{ match ($deadline->status_color) {
                                                        'red' => 'red',
                                                        'yellow' => 'amber',
                                                        'green' => 'green',
                                                        default => 'gray',
                                                    } }}">{{ $deadline->days_label }}</span>
                                            </div>
                                            <div class="dl-row">
                                                <span class="dl-date">scad.
                                                    {{ $deadline->due_date?->translatedFormat('d M') ?? '—' }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($inWorkshopVehicleIds->contains($vehicle->id))
                                    <span class="badge b-red">{{ __('In officina') }}</span>
                                @else
                                    <span class="badge b-green">{{ __('Disponibile') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($vehicle->open_issues_count > 0 && $vehicle->in_progress_issues_count > 0)
                                    <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                        <span class="badge b-red">⚠ {{ __('Aperti') }}
                                            {{ $vehicle->open_issues_count }}</span>
                                        <span class="badge b-amber">◐ {{ __('Lavoraz.') }}
                                            {{ $vehicle->in_progress_issues_count }}</span>
                                    </div>
                                @elseif($vehicle->open_issues_count > 0)
                                    <span class="badge b-red">⚠ {{ __('Aperti') }}
                                        {{ $vehicle->open_issues_count }}</span>
                                @elseif($vehicle->in_progress_issues_count > 0)
                                    <span class="badge b-amber">◐ {{ __('Lavoraz.') }}
                                        {{ $vehicle->in_progress_issues_count }}</span>
                                @else
                                    <span class="badge b-green">{{ __('OK') }}</span>
                                @endif
                            </td>
                            <td>
                                @php($missingEquipment = $vehicle->missingRequiredEquipment())
                                @if (!$vehicle->vehicleType)
                                    <span class="badge b-gray">{{ __('No tipo') }}</span>
                                @elseif ($missingEquipment->isEmpty())
                                    <span class="badge b-green">{{ __('OK') }}</span>
                                @else
                                    <span class="badge b-red"
                                        title="{{ __('Manca: ') }}{{ $missingEquipment->pluck('name')->join(', ') }}">{{ __('Da integrare') }}</span>
                                @endif
                            </td>
                            <td>
                                <x-admin.row-actions
                                    :show-url="route('admin.vehicles.show', $vehicle->id)"
                                    :edit-url="route('admin.vehicles.edit', $vehicle->id)"
                                    :delete-target="'#confirmDeleteModal-' . $vehicle->id" />
                            </td>
                        </tr>
                        <x-admin.delete-modal type="vehicle" :object="$vehicle" />
                    @empty
                        <tr>
                            <td colspan="8" class="empty">{{ __('Nessun veicolo trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vehicles->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $vehicles->currentPage() }} / {{ $vehicles->lastPage() }} ·
                    {{ $vehicles->total() }}</span>
                <nav>
                    {{ $vehicles->links() }}
                </nav>
            </div>
        @endif
    </div>

    <p class="note">
        {{ __('Le scadenze per km (cinghia, tagliando) mostrano i km mancanti al target; quelle per data mostrano giorni alla scadenza.') }}
    </p>
@endsection
