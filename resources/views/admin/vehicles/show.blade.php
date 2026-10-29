@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Veicoli'), 'url' => route('admin.vehicles.index')],
        ['label' => $vehicle->internal_code],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.vehicles.index')) }}" class="btn">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.vehicles.pdf', $vehicle->id) }}" class="btn outline-solid" target="_blank">
            <i class="fa-solid fa-file-pdf"></i> PDF
        </a>
        <a href="{{ route('admin.vehicles.qr-label', $vehicle->id) }}" class="btn outline-solid" target="_blank"
            title="{{ __('Etichetta QR da stampare e attaccare sul mezzo') }}">
            <i class="fa-solid fa-qrcode"></i> QR
        </a>
        <a href="{{ route('admin.vehicles.edit', $vehicle->id) }}" class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
    </div>

    <x-admin.vehicle-show.timing-belt-prompt :vehicle="$vehicle" />

    @php
        $revisione = $deadlines->get($deadlinesTypes['revisione']);
        $ossigeno = $deadlines->get($deadlinesTypes['ossigeno']);
        $tagliando = $deadlines->get($deadlinesTypes['tagliando']);
        $cinghia = $deadlines->get($deadlinesTypes['cinghia']);
        $assicurazione = $deadlines->get('Assicurazione');
        $activeDeadlines = collect([$revisione, $ossigeno, $tagliando, $cinghia, $assicurazione])
            ->filter()
            ->filter(fn($d) => in_array($d->automatic_status, ['pending', 'expired']));
        $missingEquipment = $vehicle->missingRequiredEquipment();
    @endphp

    {{-- Header veicolo --}}
    <div class="veh-header">
        <div class="veh-avatar"><i class="fa-solid fa-truck"></i></div>
        <div class="veh-title">
            <h1>{{ $vehicle->internal_code }}</h1>
            <div class="sub">{{ $vehicle->license_plate ?? '—' }} · {{ $vehicle->vehicleType->name ?? 'N/A' }} ·
                {{ $vehicle->fuel_type ?? '—' }}</div>
        </div>
        <div class="veh-badges">
            @if ($inWorkshop)
                <span class="badge b-red">{{ __('In officina') }}</span>
            @else
                <span class="badge b-green">{{ __('Disponibile') }}</span>
            @endif
            @if ($vehicle->open_issues->isNotEmpty())
                <span class="badge b-red">⚠
                    {{ __(':count guasto/i aperto/i', ['count' => $vehicle->open_issues->count()]) }}</span>
            @endif
            @if (!$vehicle->vehicleType)
                <span class="badge b-gray">{{ __('Nessun tipo assegnato') }}</span>
            @elseif ($missingEquipment->isNotEmpty())
                <span class="badge b-gray" title="{{ __('Manca: ') }}{{ $missingEquipment->pluck('name')->join(', ') }}">
                    {{ __('Equip. da integrare') }}</span>
            @endif
        </div>
        <div class="veh-meta">
            <div class="meta-item">
                <div class="lbl">{{ __('Km attuali') }}</div>
                <div class="val">{{ number_format($vehicle->mileage ?? 0, 0, ',', '.') }}</div>
            </div>
            <div class="meta-item">
                <div class="lbl">{{ __('Immatricol.') }}</div>
                <div class="val">{{ $vehicle->immatricolation_date?->format('m/Y') ?? '—' }}</div>
            </div>
            <div class="meta-item">
                <div class="lbl">{{ __('Garanzia') }}</div>
                <div class="val" style="color:{{ $vehicle->is_warranty_expired ? 'var(--red)' : 'var(--green)' }}">
                    {{ $vehicle->is_warranty_expired ? '✕ '.__('Scaduta') : '✓ '.__('Attiva') }}
                </div>
            </div>
        </div>
    </div>

    {{--
        Scheda a tab invece di 11 card impilate in scroll continuo (restyle
        "Saru", ADR §19). Il contenuto di ogni tab resta renderizzato lato
        server come prima (nessuna rimozione dal DOM): solo mostrato/
        nascosto via Bootstrap Tab (data-bs-toggle="tab"), così i test
        Feature che leggono il contenuto della pagina restano validi senza
        modifiche.
    --}}
    <div class="veh-tabs">
        <ul class="nav veh-tab-nav" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="veh-tab-panoramica-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-panoramica" type="button" role="tab" aria-controls="veh-tab-panoramica"
                    aria-selected="true">{{ __('Panoramica') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="veh-tab-scadenze-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-scadenze" type="button" role="tab" aria-controls="veh-tab-scadenze"
                    aria-selected="false">{{ __('Scadenze') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="veh-tab-manutenzioni-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-manutenzioni" type="button" role="tab" aria-controls="veh-tab-manutenzioni"
                    aria-selected="false">{{ __('Manutenzioni') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="veh-tab-pneumatici-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-pneumatici" type="button" role="tab" aria-controls="veh-tab-pneumatici"
                    aria-selected="false">{{ __('Pneumatici') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="veh-tab-attrezzatura-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-attrezzatura" type="button" role="tab" aria-controls="veh-tab-attrezzatura"
                    aria-selected="false">{{ __('Attrezzatura') }}</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="veh-tab-storico-btn" data-bs-toggle="tab"
                    data-bs-target="#veh-tab-storico" type="button" role="tab" aria-controls="veh-tab-storico"
                    aria-selected="false">{{ __('Storico') }}</button>
            </li>
        </ul>

        <div class="tab-content veh-tab-content">
            <div class="tab-pane fade show active" id="veh-tab-panoramica" role="tabpanel"
                aria-labelledby="veh-tab-panoramica-btn">
                <div class="veh-grid">
                    <x-admin.vehicle-show.registry-card :vehicle="$vehicle" />
                    <x-admin.vehicle-show.tech-specs-card :vehicle="$vehicle" />
                    <x-admin.vehicle-show.documents-card :vehicle="$vehicle" />
                    <x-admin.vehicle-show.reliability-trend-card :reliability-trend="$reliabilityTrend" />
                </div>
            </div>

            <div class="tab-pane fade" id="veh-tab-scadenze" role="tabpanel" aria-labelledby="veh-tab-scadenze-btn">
                <div class="veh-grid">
                    <x-admin.vehicle-show.deadlines-active-card :vehicle="$vehicle" :active-deadlines="$activeDeadlines" />
                    <x-admin.vehicle-show.deadlines-status-card :deadlines="$deadlines" />
                </div>
            </div>

            <div class="tab-pane fade" id="veh-tab-manutenzioni" role="tabpanel"
                aria-labelledby="veh-tab-manutenzioni-btn">
                <div class="veh-grid">
                    <x-admin.vehicle-show.issues-card :vehicle="$vehicle" :issue-providers="$issueProviders" />
                </div>
                <x-admin.vehicle-show.appointments-history-card :appointments="$vehicleAppointments" />
            </div>

            <div class="tab-pane fade" id="veh-tab-pneumatici" role="tabpanel" aria-labelledby="veh-tab-pneumatici-btn">
                <div class="veh-grid">
                    <x-admin.vehicle-show.tires-card :vehicle="$vehicle" />
                </div>
            </div>

            <div class="tab-pane fade" id="veh-tab-attrezzatura" role="tabpanel"
                aria-labelledby="veh-tab-attrezzatura-btn">
                <div class="veh-grid">
                    <x-admin.vehicle-show.equipment-card :vehicle="$vehicle" :assignable-equipment="$assignableEquipment"
                        :missing-equipment="$missingEquipment" />
                </div>
            </div>

            <div class="tab-pane fade" id="veh-tab-storico" role="tabpanel" aria-labelledby="veh-tab-storico-btn">
                <x-admin.vehicle-show.deadlines-history-card :vehicle="$vehicle" />
                <x-admin.vehicle-show.tire-changes-history-card :tire-changes="$vehicle->tireChanges" />
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ogni voce del menu "assegna attrezzatura" è un piccolo form a sé
            // (il pulsante invia direttamente): se l'attrezzatura scelta è già
            // assegnata a un altro veicolo, chiede conferma prima di spostarla.
            document.querySelectorAll('.eq-assign-form').forEach((form) => {
                form.addEventListener('submit', function(event) {
                    const button = form.querySelector('button[type="submit"]');
                    if (button.dataset.assigned === '1') {
                        const confirmed = confirm(
                            @json(__('Questa attrezzatura è già assegnata a')) + ' ' + button.dataset.assignedTo +
                            '. ' + @json(__('Spostarla su questo veicolo?'))
                        );
                        if (!confirmed) {
                            event.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
@endsection
