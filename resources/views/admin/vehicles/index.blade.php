@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Veicoli')],
    ]" />
@endsection

@section('content')

    {{-- Stats KPI --}}
    <div class="stats">
        <div class="stat">
            <div class="kpi k1"><i class="fa-solid fa-truck"></i></div>
            <div>
                <div class="lbl">{{ __('Veicoli attivi') }}</div>
                <div class="val">{{ $totalVehicles }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="kpi k2"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="lbl">{{ __('Guasti aperti') }}</div>
                <div class="val">{{ $openIssuesCount }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="kpi k3"><i class="fa-solid fa-clock"></i></div>
            <div>
                <div class="lbl">{{ __('Scadenze in 30gg') }}</div>
                <div class="val">{{ $deadlinesIn30 }}</div>
            </div>
        </div>
        <div class="stat">
            <div class="kpi k4"><i class="fa-solid fa-check"></i></div>
            <div>
                <div class="lbl">{{ __('Flotta completa') }}</div>
                <div class="val">{{ $completeFleet }}</div>
            </div>
        </div>
    </div>

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Veicoli') }}</h2>
                <div class="filters">
                    @php($chipBase = request()->except(['filter', 'page']))
                    <a href="{{ route('admin.vehicles.index', $chipBase) }}"
                        class="chip {{ $filter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'issues'])) }}"
                        class="chip {{ $filter === 'issues' ? 'on' : '' }}">{{ __('Guasti') }}</a>
                    <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'deadline'])) }}"
                        class="chip {{ $filter === 'deadline' ? 'on' : '' }}">{{ __('Scadenza prossima') }}</a>
                    <a href="{{ route('admin.vehicles.index', array_merge($chipBase, ['filter' => 'incomplete'])) }}"
                        class="chip {{ $filter === 'incomplete' ? 'on' : '' }}">{{ __('Da integrare') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.vehicles.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca sigla o targa') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.vehicles.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                <a href="{{ route('admin.csv.export', 'vehicles') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.vehicles.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo') }}
                </a>
            </div>
        </div>

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
                                        <div class="vin-sub">{{ $vehicle->immatricolation_date?->format('Y') ?? '—' }}
                                            · {{ $vehicle->fuel_type ?? '—' }}</div>
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
                                @if ($vehicle->open_issues_count > 0 && $vehicle->in_progress_issues_count > 0)
                                    <span class="badge b-red">⚠ {{ __('Aperti') }}
                                        {{ $vehicle->open_issues_count }} + {{ __('Lavoraz.') }}
                                        {{ $vehicle->in_progress_issues_count }}</span>
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
                                <div class="row-actions">
                                    <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="mini-btn"
                                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                    <a href="{{ route('admin.vehicles.edit', $vehicle->id) }}" class="mini-btn"
                                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                                        data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $vehicle->id }}"><i
                                            class="fa-solid fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <x-admin.delete-modal type="vehicle" :object="$vehicle" />
                    @empty
                        <tr>
                            <td colspan="7" class="empty">{{ __('Nessun veicolo trovato.') }}</td>
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
