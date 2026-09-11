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
        <a href="{{ route('admin.vehicles.edit', $vehicle->id) }}" class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
    </div>

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
                <span class="badge b-red">⚠ {{ $vehicle->open_issues->count() }}
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

    {{-- Anagrafica, Documenti, Scadenze --}}
    <div class="veh-grid">
        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Anagrafica') }}</h3>
            </div>
            <div class="body">
                <div class="veh-kv"><span class="k">{{ __('Targa') }}</span><span
                        class="v">{{ $vehicle->license_plate ?? '—' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Marca') }}</span><span
                        class="v">{{ $vehicle->brand->name ?? 'N/A' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Modello') }}</span><span
                        class="v">{{ $vehicle->carModel->name ?? 'N/A' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Carburante') }}</span><span
                        class="v">{{ $vehicle->fuel_type ?? '—' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Tipo') }}</span><span
                        class="v">{{ $vehicle->vehicleType->name ?? 'N/A' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Cinghia distribuzione') }}</span><span class="v">
                        <span class="{{ $vehicle->has_timing_belt ? 'ok' : 'no' }}">
                            {{ $vehicle->has_timing_belt ? __('Sì') : __('No') }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Documenti') }}</h3>
            </div>
            <div class="body">
                <div class="veh-kv"><span class="k">{{ __('Immatricolazione') }}</span><span
                        class="v">{{ $vehicle->immatricolation_date_formatted ?? 'N/A' }}</span></div>
                <div class="veh-kv"><span class="k">{{ __('Carta circolazione') }}</span><span class="v">
                        @if ($vehicle->registration_card_path)
                            <a href="{{ Storage::url($vehicle->registration_card_path) }}" target="_blank"
                                rel="noopener noreferrer">{{ __('Apri file') }}</a>
                        @else
                            N/A
                        @endif
                    </span></div>
                <div class="veh-kv"><span class="k">{{ __('Garanzia') }}</span><span class="v">
                        <span class="{{ $vehicle->is_warranty_expired ? 'no' : 'ok' }}">
                            {{ $vehicle->is_warranty_expired ? '✕' : '✓' }}
                            {{ $vehicle->warranty_expiration_date_formatted ?? 'N/A' }}
                        </span>
                    </span></div>
                @if ($vehicle->has_warranty_extension)
                    <div class="veh-kv"><span class="k">{{ __('Estensione garanzia') }}</span><span
                            class="v">+{{ $vehicle->warranty_extension_duration }} {{ __('mesi') }}</span></div>
                @endif
            </div>
        </div>

        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Scadenze') }}</h3>
                <a href="{{ route('admin.deadlines.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
                    class="veh-btn-add" title="{{ __('Nuova scadenza') }}"><i class="fa-solid fa-plus"></i></a>
            </div>
            <div class="body">
                @forelse ($activeDeadlines as $deadline)
                    <div class="veh-dl-item">
                        <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                        <div style="min-width:0;">
                            <div class="name">{{ $deadline->type }}</div>
                            <div class="date">{{ __('scad.') }} {{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</div>
                        </div>
                        <div class="veh-dl-badges">
                            <span
                                class="count c-{{ match ($deadline->status_color) {
                                    'red' => 'red',
                                    'yellow' => 'amber',
                                    'green' => 'green',
                                    default => 'gray',
                                } }}">{{ $deadline->days_label }}</span>
                            <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                                title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.deadlines.edit', $deadline->id) }}" class="mini-btn"
                                title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="veh-dl-item">
                        <div><div class="name">{{ __('Nessuna scadenza attiva') }}</div></div>
                    </div>
                @endforelse
                @if ($assicurazione)
                    <div class="veh-dl-item">
                        <div style="min-width:0;">
                            <div class="name">{{ __('Assicurazione') }}</div>
                            <div class="date">{{ __('scad.') }} {{ $assicurazione->due_date_formatted ?? '—' }}</div>
                        </div>
                        <div class="veh-dl-badges">
                            <a href="{{ route('admin.deadlines.show', $assicurazione->id) }}" class="mini-btn"
                                title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.deadlines.edit', $assicurazione->id) }}" class="mini-btn"
                                title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Stato Scadenze (ultima per tipo), Guasti, Equipaggiamento --}}
    <div class="veh-grid">
        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Stato Scadenze') }}</h3>
            </div>
            <div class="body">
                @forelse ($deadlines as $deadline)
                    <div class="veh-dl-item">
                        <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                        <div style="min-width:0;">
                            <div class="name">{{ $deadline->type }}</div>
                            <div class="date">{{ __('scad.') }} {{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</div>
                        </div>
                        <div class="veh-dl-badges">
                            <span
                                class="count c-{{ match ($deadline->status_color) {
                                    'red' => 'red',
                                    'yellow' => 'amber',
                                    'green' => 'green',
                                    default => 'gray',
                                } }}">{{ $deadline->status_label }}</span>
                            <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                                title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="veh-dl-item"><div><div class="name">{{ __('Nessuna scadenza registrata') }}</div></div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Guasti') }}
                    @if ($vehicle->issues->where('status', 'open')->isNotEmpty())
                        <span class="count c-red">{{ $vehicle->issues->where('status', 'open')->count() }}</span>
                    @endif
                    @if ($vehicle->issues->where('status', 'in_progress')->isNotEmpty())
                        <span class="count c-amber">{{ $vehicle->issues->where('status', 'in_progress')->count() }}</span>
                    @endif
                </h3>
                <a href="{{ route('admin.issues.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
                    class="veh-btn-add" title="{{ __('Nuovo guasto') }}"><i class="fa-solid fa-plus"></i></a>
            </div>
            <div class="body veh-issue-scroll">
                @php($issueStatusClasses = ['open' => 'open', 'in_progress' => 'work'])
                @forelse ($vehicle->issues->sortByDesc('event_date') as $issue)
                    @php($issueProvider = $issueProviders->get($issue->id))
                    <div class="veh-issue-item {{ $issueStatusClasses[$issue->status] ?? 'done' }}">
                        <div>
                            <div class="date">{{ $issue->event_date_formatted ?? 'N/A' }}</div>
                        </div>
                        <span class="desc">{{ $issue->description }}
                            @if ($issueProvider)
                                <span class="meta">
                                    <a href="{{ route('admin.providers.show', ['provider' => $issueProvider->id, 'back' => url()->full()]) }}"
                                        style="color:inherit;">{{ $issueProvider->name }}</a>
                                </span>
                            @endif
                        </span>
                        <span
                            class="badge {{ match ($issue->status_color) {
                                'red' => 'b-red',
                                'yellow' => 'b-amber',
                                'green' => 'b-green',
                                default => 'b-gray',
                            } }}">{{ $issue->status_label }}</span>
                        <div class="row-actions">
                            <a href="{{ route('admin.issues.show', ['issue' => $issue->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal-{{ $issue->id }}"><i
                                    class="fa-solid fa-trash"></i></button>
                        </div>
                        <x-admin.delete-modal type="issue" :object="$issue" />
                    </div>
                @empty
                    <div class="veh-issue-item done"><span class="desc">{{ __('Nessun guasto registrato') }}</span></div>
                @endforelse
            </div>
        </div>

        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Equipaggiamento') }}</h3>
                <a href="{{ route('admin.equipments.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
                    class="veh-btn-add" title="{{ __('Nuova attrezzatura') }}"><i class="fa-solid fa-plus"></i></a>
            </div>
            <div class="body">
                @forelse ($vehicle->equipment as $equipment)
                    <div class="veh-eq-item">
                        <span class="ic"><i class="fa-solid fa-toolbox"></i></span>
                        <div style="min-width:0;">
                            <div class="name">{{ $equipment->equipmentType->name ?? $equipment->name ?? 'N/A' }}</div>
                            <div class="meta">{{ $equipment->serial_number ?? 'N/A' }} ·
                                {{ __('rev.') }} {{ $equipment->revision_date_formatted ?? 'N/A' }}</div>
                        </div>
                        <span
                            class="exp c-{{ match ($equipment->status_color) {
                                'red' => 'red',
                                'yellow' => 'amber',
                                'green' => 'green',
                                default => 'gray',
                            } }}">{{ $equipment->status_label }}</span>
                        <div class="row-actions">
                            <a href="{{ route('admin.equipments.show', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal-{{ $equipment->id }}"><i
                                    class="fa-solid fa-trash"></i></button>
                        </div>
                        <x-admin.delete-modal type="equipment" :object="$equipment" />
                    </div>
                @empty
                    <div class="veh-eq-item"><div><div class="name">{{ __('Nessun equipaggiamento registrato') }}</div>
                        </div></div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Storico Scadenze: tabella dettagliata per tipo --}}
    @if ($vehicle->deadlines->isNotEmpty())
        <div class="table-card" style="margin-top:16px;">
            <div class="toolbar">
                <h2>{{ __('Storico Scadenze') }}</h2>
            </div>
            <div class="table-responsive" style="padding:12px 16px;">
                @foreach ($vehicle->deadlines->sortByDesc('due_date')->groupBy('type') as $type => $typeDeadlines)
                    <h3 style="font-size:13px;font-weight:600;margin:12px 0 4px;">{{ $type }}</h3>
                    <table class="veh-hist-table">
                        <thead>
                            <tr>
                                <th>{{ __('Data scadenza') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th>{{ __('Rinnovata') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($typeDeadlines as $deadline)
                                <tr>
                                    <td>{{ $deadline->due_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                    <td>
                                        <span
                                            class="badge {{ match ($deadline->status_color) {
                                                'red' => 'b-red',
                                                'yellow' => 'b-amber',
                                                'green' => 'b-green',
                                                default => 'b-gray',
                                            } }}">{{ $deadline->status_label }}</span>
                                    </td>
                                    <td>
                                        @if ($deadline->is_renewed)
                                            <i class="fa-solid fa-check" style="color:var(--green)"></i>
                                        @else
                                            <i class="fa-solid fa-xmark" style="color:var(--text-muted)"></i>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        </div>
    @endif
@endsection
