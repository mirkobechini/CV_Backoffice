@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Manutenzione')],
        ['label' => __('Guasti'), 'url' => route('admin.issues.index')],
        ['label' => $issue->description],
    ]" />
@endsection

@section('detail-topbar-title', __('Dettaglio guasto'))
@section('detail-topbar-back-url', request('back', route('admin.issues.index')))
@section('detail-topbar-actions')
    @if ($issue->status !== 'closed')
        <a href="{{ route('admin.maintenance-records.create', ['issue_id' => $issue->id, 'vehicle_id' => $issue->vehicle_id, 'back' => url()->full()]) }}"
            title="{{ __('Prenota appuntamento') }}" aria-label="{{ __('Prenota appuntamento') }}">
            <i class="fa-solid fa-calendar-check"></i>
        </a>
    @endif
    <a href="{{ route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()]) }}"
        title="{{ __('Modifica') }}" aria-label="{{ __('Modifica') }}">
        <i class="fa-solid fa-pen"></i>
    </a>
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.issues.index')) }}" class="btn ghost page-actions-back">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        @if ($issue->status !== 'closed')
            <a class="btn success page-actions-extra"
                href="{{ route('admin.maintenance-records.create', ['issue_id' => $issue->id, 'vehicle_id' => $issue->vehicle_id, 'back' => url()->full()]) }}">
                <i class="fa-solid fa-calendar-check"></i> {{ __('Prenota appuntamento') }}
            </a>
        @endif
        <a href="{{ route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()]) }}" class="btn">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--red),var(--guasto));"><i
                class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="dl-title">
            <h1>{{ $issue->description }}</h1>
            <div class="sub">{{ $issue->vehicle->internal_code ?? 'N/A' }}
                @if ($issue->vehicle)
                    · {{ $issue->vehicle->brand?->name ?? 'N/A' }} {{ $issue->vehicle->carModel?->name ?? '' }}
                @endif
            </div>
        </div>
        <div class="dl-status">
            <x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge>
            <span class="hint">{{ __('dal :d', ['d' => $issue->event_date_formatted ?? 'N/A']) }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli guasto') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($issue->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $issue->vehicle->id) }}">
                            {{ $issue->vehicle->internal_code }} · {{ $issue->vehicle->license_plate }} ·
                            {{ $issue->vehicle->brand?->name ?? 'N/A' }} {{ $issue->vehicle->carModel?->name ?? '' }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            @if ($issue->tires->isNotEmpty())
                <div class="dl-kv">
                    <span class="k">{{ __('Pneumatici collegati') }}</span>
                    <span class="v">
                        @foreach ($issue->tires as $tire)
                            <a class="dl-veh-link" href="{{ route('admin.tires.show', $tire->id) }}">
                                {{ $tire->position_label }} · {{ $tire->season_label }}
                                @if ($tire->brand)
                                    · {{ $tire->brand }}
                                @endif
                                <span class="arrow">›</span>
                            </a>
                        @endforeach
                    </span>
                </div>
            @endif
            <div class="dl-kv">
                <span class="k">{{ __('Parte coinvolta') }}</span>
                <span class="v">{{ $issue->part ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data del guasto') }}</span>
                <span class="v">{{ $issue->event_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge></span>
            </div>
        </div>
    </div>

    @if ($issue->notes)
        <div class="notes-card">
            <h4>{{ __('Note') }}</h4>
            <p>{{ $issue->notes }}</p>
        </div>
    @endif

    @if ($issue->photo)
        <div class="photo-card">
            <h4>{{ __('Immagine') }}</h4>
            <img src="{{ \App\Support\UploadedFileUrl::for($issue->photo) }}"
                alt="{{ __('Immagine del guasto') }}">
        </div>
    @endif

    @if ($activities->isNotEmpty())
        @php
            $statusEvent = fn($status) => match ($status) {
                'in_progress' => ['label' => __('Preso in carico'), 'color' => 'amber'],
                'closed' => ['label' => __('Risolto'), 'color' => 'green'],
                'open' => ['label' => __('Riaperto'), 'color' => 'red'],
                default => ['label' => __('Aggiornato'), 'color' => 'gray'],
            };
            $timelineEntries = collect();
            foreach ($activities as $activity) {
                if ($activity->event === 'created') {
                    // "Segnalato" riporta la data del guasto (quando è
                    // successo, scelta nel form), non il timestamp interno
                    // di creazione del record: altrimenti non coinciderebbe
                    // con "Data del guasto" nella card Dettagli, confondendo.
                    //
                    // Un guasto inserito già "In lavorazione"/"Risolto" (non
                    // passato per lo stato "Aperto") non ha mai generato un
                    // evento di cambio stato separato: senza questo, lo
                    // storico mostrerebbe solo "Segnalato" anche se il badge
                    // dice "In lavorazione", un'incoerenza che confonde.
                    // Spinto prima di "Segnalato" (stesso istante) perché lo
                    // storico è ordinato dal più recente: lo stato raggiunto
                    // va sopra all'evento che lo ha originato.
                    $initialStatus = $activity->attribute_changes?->get('attributes', [])['status'] ?? null;
                    if ($initialStatus && $initialStatus !== 'open') {
                        $timelineEntries->push([...$statusEvent($initialStatus), 'date' => $issue->event_date]);
                    }
                    $timelineEntries->push(['label' => __('Segnalato'), 'color' => 'red', 'date' => $issue->event_date]);

                    continue;
                }
                $newStatus = $activity->attribute_changes?->get('attributes', [])['status'] ?? null;
                $oldStatus = $activity->attribute_changes?->get('old', [])['status'] ?? null;
                $event = ($newStatus && $newStatus !== $oldStatus)
                    ? $statusEvent($newStatus)
                    : ['label' => __('Aggiornato'), 'color' => 'gray'];
                $timelineEntries->push([...$event, 'date' => $activity->created_at]);
            }
            $timelineEntries = $timelineEntries->sortByDesc('date');
        @endphp
        <div class="timeline-card">
            <h4>{{ __('Storico') }}</h4>
            <ul class="timeline">
                @foreach ($timelineEntries as $event)
                    <li class="timeline-item">
                        <span class="timeline-dot {{ $event['color'] }}"></span>
                        <div>
                            <span class="timeline-label">{{ $event['label'] }}</span>
                            <span class="timeline-date">{{ $event['date']->format('d/m/Y') }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-admin.delete-modal type="issue" :object="$issue" />
@endsection
