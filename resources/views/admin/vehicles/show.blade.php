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

    @if (session('timingBeltPrompt'))
        @php
            $timingBeltPrompt = session('timingBeltPrompt');
        @endphp
        <div class="modal fade" id="timingBeltPromptModal" tabindex="-1"
            aria-labelledby="timingBeltPromptModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="timingBeltPromptModalLabel">
                            {{ __('Cinghia di distribuzione') }}</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>
                    <div class="modal-body">
                        @if ($timingBeltPrompt['action'] === 'create')
                            {{ __('Il veicolo è ora segnato come dotato di cinghia di distribuzione, ma non ha ancora una scadenza cinghia collegata. Vuoi crearla ora, calcolata dalla data di immatricolazione?') }}
                        @elseif ($timingBeltPrompt['action'] === 'delete')
                            {{ __('Il veicolo non è più segnato come dotato di cinghia di distribuzione, ma esiste ancora una scadenza cinghia attiva (scad. :date). Vuoi eliminarla?', ['date' => $timingBeltPrompt['due_date']]) }}
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Ignora') }}</button>
                        @if ($timingBeltPrompt['action'] === 'create')
                            <form method="POST" action="{{ route('admin.vehicles.timing-belt-deadline.create', $vehicle) }}"
                                class="m-0">
                                @csrf
                                <button type="submit" class="btn btn-primary">{{ __('Crea scadenza cinghia') }}</button>
                            </form>
                        @elseif ($timingBeltPrompt['action'] === 'delete')
                            <form method="POST" action="{{ route('admin.deadlines.destroy', $timingBeltPrompt['deadline_id']) }}"
                                class="m-0">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="back" value="{{ route('admin.vehicles.show', $vehicle->id) }}">
                                <button type="submit" class="btn btn-danger">{{ __('Elimina scadenza cinghia') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalEl = document.getElementById('timingBeltPromptModal');
                if (modalEl) {
                    new bootstrap.Modal(modalEl).show();
                }
            });
        </script>
    @endif

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
                @php
                    $issueStatusClasses = ['open' => 'open', 'in_progress' => 'work'];

                    // Guasti aperti/in lavorazione sempre in cima alla card,
                    // indipendentemente dalla data: sono quelli che
                    // richiedono attenzione. All'interno di ciascun gruppo
                    // (aperti+in lavorazione, poi chiusi) restano ordinati
                    // per data più recente, grazie alla stabilità di
                    // sortBy() sopra un array già ordinato per data.
                    $sortedIssues = $vehicle->issues
                        ->sortByDesc(fn($issue) => $issue->event_date?->format('Y-m-d') ?? '')
                        ->sortBy(fn($issue) => in_array($issue->status, ['open', 'in_progress'], true) ? 0 : 1)
                        ->values();
                @endphp
                @forelse ($sortedIssues as $issue)
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
                @if ($missingEquipment->isNotEmpty())
                    @foreach ($missingEquipment as $missingType)
                        @php($availableQuantity = $vehicle->equipment->where('equipment_type_id', $missingType->id)->count())
                        @php($requiredQuantity = (int) $missingType->pivot->required_quantity)
                        <div class="veh-eq-item">
                            <span class="ic" style="color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i></span>
                            <div style="min-width:0;">
                                <div class="name">{{ $missingType->name }}</div>
                                <div class="meta">{{ __('Presenti :available di :required richieste', ['available' => $availableQuantity, 'required' => $requiredQuantity]) }}</div>
                            </div>
                            <span class="exp c-red">{{ __('Mancante') }}</span>
                            <div class="row-actions">
                                <a href="{{ route('admin.equipments.create', ['vehicle_id' => $vehicle->id, 'equipment_type_id' => $missingType->id, 'back' => url()->full()]) }}"
                                    class="mini-btn" title="{{ __('Aggiungi') }}"><i class="fa-solid fa-plus"></i></a>
                            </div>
                        </div>
                    @endforeach
                @endif
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

                @if ($assignableEquipment->isNotEmpty())
                    <form method="POST" action="{{ route('admin.vehicles.equipment.assign', $vehicle) }}"
                        id="assign-equipment-form" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);">
                        @csrf
                        <div class="field" style="margin-bottom:8px;">
                            <label for="assign_equipment_id">{{ __('Assegna attrezzatura esistente') }}</label>
                            <select class="select @error('equipment_id') is-invalid @enderror" id="assign_equipment_id"
                                name="equipment_id" required>
                                <option value="" disabled selected>{{ __('Seleziona attrezzatura...') }}</option>
                                @foreach ($assignableEquipment as $assignable)
                                    <option value="{{ $assignable->id }}"
                                        data-assigned="{{ $assignable->vehicle_id ? '1' : '0' }}"
                                        data-assigned-to="{{ $assignable->vehicle->internal_code ?? '' }}">
                                        {{ $assignable->equipmentType->name ?? $assignable->name }} ·
                                        {{ $assignable->serial_number ?? 'N/A' }}
                                        @if ($assignable->vehicle)
                                            — {{ __('su') }} {{ $assignable->vehicle->internal_code }}
                                        @else
                                            — {{ __('non assegnata') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('equipment_id')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <label class="check" style="margin-bottom:8px;">
                            <input type="checkbox" id="assign-unassigned-only">
                            <div>
                                <div class="label">{{ __('Mostra solo non assegnate') }}</div>
                            </div>
                        </label>
                        <button type="submit" class="btn ghost sm">
                            <i class="fa-solid fa-link"></i> {{ __('Assegna') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="veh-card">
            <div class="head">
                <h3>{{ __('Pneumatici') }}</h3>
                <div style="display:flex; gap:6px;">
                    <a href="{{ route('admin.maintenance-records.create', ['vehicle_id' => $vehicle->id, 'activity_type' => 'Cambio Gomme', 'back' => url()->full()]) }}"
                        class="veh-btn-add" title="{{ __('Registra cambio gomme') }}"><i
                            class="fa-solid fa-arrows-rotate"></i></a>
                    <a href="{{ route('admin.tires.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
                        class="veh-btn-add" title="{{ __('Nuovo set di gomme') }}"><i class="fa-solid fa-plus"></i></a>
                </div>
            </div>
            <div class="body">
                @forelse ($vehicle->tires->sortByDesc('status') as $tire)
                    <div class="veh-eq-item">
                        <span class="ic"><i class="fa-solid fa-circle-dot"></i></span>
                        <div style="min-width:0;">
                            <div class="name">{{ $tire->season_label }}
                                ({{ $tire->position_label }})
                                ·
                                {{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: __('N/A') }}</div>
                            <div class="meta">{{ $tire->size ?? 'N/A' }} ·
                                {{ __('cambio') }} {{ $tire->next_change_date_formatted ?? 'N/A' }}</div>
                        </div>
                        <span
                            class="exp c-{{ match ($tire->status) {
                                'mounted' => 'green',
                                'stored' => 'gray',
                                'retired' => 'red',
                                default => 'gray',
                            } }}">{{ $tire->status_label }}</span>
                        <div class="row-actions">
                            <a href="{{ route('admin.tires.show', ['tire' => $tire->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                            <a href="{{ route('admin.tires.edit', ['tire' => $tire->id, 'back' => url()->full()]) }}"
                                class="mini-btn" title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                            <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                                data-bs-target="#confirmDeleteModal-{{ $tire->id }}"><i
                                    class="fa-solid fa-trash"></i></button>
                        </div>
                        <x-admin.delete-modal type="tire" :object="$tire" />
                    </div>
                @empty
                    <div class="veh-eq-item"><div><div class="name">{{ __('Nessun set di gomme registrato') }}</div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('assign-equipment-form');
            if (!form) {
                return;
            }

            const select = document.getElementById('assign_equipment_id');
            const unassignedOnly = document.getElementById('assign-unassigned-only');

            const applyFilter = () => {
                Array.from(select.options).forEach((option) => {
                    if (!option.value) {
                        return;
                    }
                    option.hidden = unassignedOnly.checked && option.dataset.assigned === '1';
                });
                if (select.selectedOptions[0]?.hidden) {
                    select.value = '';
                }
            };

            unassignedOnly.addEventListener('change', applyFilter);

            form.addEventListener('submit', function(event) {
                const selected = select.options[select.selectedIndex];
                if (selected && selected.dataset.assigned === '1') {
                    const confirmed = confirm(
                        @json(__('Questa attrezzatura è già assegnata a')) + ' ' + selected.dataset.assignedTo +
                        '. ' + @json(__('Spostarla su questo veicolo?'))
                    );
                    if (!confirmed) {
                        event.preventDefault();
                    }
                }
            });
        });
    </script>
@endsection
