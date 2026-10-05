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
        <a href="{{ route('admin.vehicles.pdf', $vehicle->id) }}" class="btn outline" target="_blank">
            <i class="fa-solid fa-file-pdf"></i> PDF
        </a>
        <a href="{{ route('admin.vehicles.qr-label', $vehicle->id) }}" class="btn outline" target="_blank"
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
        $activeDeadlines = collect([$revisione, $ossigeno, $tagliando, $cinghia])
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

    {{-- Anagrafica, Specifiche, Documenti, Scadenze --}}
    <div class="veh-grid">
        <x-admin.vehicle-show.registry-card :vehicle="$vehicle" />
        <x-admin.vehicle-show.tech-specs-card :vehicle="$vehicle" />
        <x-admin.vehicle-show.documents-card :vehicle="$vehicle" />
        <x-admin.vehicle-show.deadlines-active-card :vehicle="$vehicle" :active-deadlines="$activeDeadlines"
            :assicurazione="$assicurazione" />
    </div>

    {{-- Stato Scadenze (ultima per tipo), Guasti, Equipaggiamento, Pneumatici --}}
    <div class="veh-grid veh-grid-capped">
        <x-admin.vehicle-show.deadlines-status-card :deadlines="$deadlines" />
        <x-admin.vehicle-show.issues-card :vehicle="$vehicle" :issue-providers="$issueProviders" />
        <x-admin.vehicle-show.equipment-card :vehicle="$vehicle" :assignable-equipment="$assignableEquipment"
            :missing-equipment="$missingEquipment" />
        <x-admin.vehicle-show.tires-card :vehicle="$vehicle" />
    </div>

    <x-admin.vehicle-show.deadlines-history-card :vehicle="$vehicle" />

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
