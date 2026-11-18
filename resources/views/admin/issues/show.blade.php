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
            @if ($issue->spare_parts_badge)
                <x-admin.status-badge
                    :color="$issue->spare_parts_badge['color']">{{ $issue->spare_parts_badge['label'] }}</x-admin.status-badge>
            @endif
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

    <div class="dl-info-card" style="margin-top:16px;">
        <div class="head">
            <h3>{{ __('Pezzi ordinati') }}</h3>
        </div>
        <div class="body">
            @if ($issue->spareParts->isNotEmpty())
                @foreach ($issue->spareParts as $part)
                    <div class="spare-part-item"
                        style="border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                            <div>
                                <strong>{{ $part->name }}</strong>
                                @if ($part->notes)
                                    <div class="hint">{{ $part->notes }}</div>
                                @endif
                            </div>
                            <form method="POST"
                                action="{{ route('admin.issues.spare-parts.destroy', [$issue, $part]) }}"
                                onsubmit="return confirm('{{ __('Eliminare questo pezzo?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mini-btn" title="{{ __('Elimina pezzo') }}"
                                    aria-label="{{ __('Elimina pezzo') }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <div class="row2-tight" style="margin-top:10px;">
                            <form class="field" method="POST"
                                action="{{ route('admin.issues.spare-parts.update', [$issue, $part]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $part->name }}">
                                <input type="hidden" name="notes" value="{{ $part->notes }}">
                                <input type="hidden" name="arrived_at"
                                    value="{{ $part->arrived_at?->format('Y-m-d') }}">
                                <label for="ordered_at-{{ $part->id }}">{{ __('Ordinato il') }}</label>
                                <input type="date" class="input" id="ordered_at-{{ $part->id }}" name="ordered_at"
                                    value="{{ $part->ordered_at?->format('Y-m-d') }}" onchange="this.form.submit()">
                            </form>
                            <form class="field" method="POST"
                                action="{{ route('admin.issues.spare-parts.update', [$issue, $part]) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $part->name }}">
                                <input type="hidden" name="notes" value="{{ $part->notes }}">
                                <input type="hidden" name="ordered_at"
                                    value="{{ $part->ordered_at?->format('Y-m-d') }}">
                                <label for="arrived_at-{{ $part->id }}">{{ __('Arrivato il') }}</label>
                                <input type="date" class="input" id="arrived_at-{{ $part->id }}" name="arrived_at"
                                    value="{{ $part->arrived_at?->format('Y-m-d') }}" onchange="this.form.submit()">
                            </form>
                        </div>
                    </div>
                @endforeach
            @else
                <p class="hint">{{ __('Nessun pezzo registrato.') }}</p>
            @endif

            <details style="margin-top:12px;" @if ($errors->has('parts.*')) open @endif>
                <summary>{{ __('+ Aggiungi pezzi di ricambio') }}</summary>
                <p class="hint" style="margin-top:8px;">
                    {{ __('Se lasci vuota la data ordine su una riga, erediterà quella della prima riga compilata. La data arrivo resta sempre facoltativa.') }}
                </p>
                @if ($errors->has('parts.*'))
                    <div class="field-error" style="margin-top:8px;">
                        @foreach ($errors->get('parts.*') as $messages)
                            @foreach ($messages as $message)
                                <div>{{ $message }}</div>
                            @endforeach
                        @endforeach
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.issues.spare-parts.store', $issue) }}"
                    id="spare-parts-form" style="margin-top:10px;">
                    @csrf
                    <div id="spare-parts-rows">
                        <div class="spare-part-row"
                            style="border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:10px;">
                            <div class="field">
                                <label for="parts-0-name">{{ __('Nome pezzo') }}</label>
                                <input type="text" class="input" id="parts-0-name" name="parts[0][name]"
                                    placeholder="{{ __('es. Specchietto destro') }}" required>
                            </div>
                            <div class="field" style="margin-top:10px;">
                                <label for="parts-0-notes">{{ __('Nota (opzionale)') }}</label>
                                <input type="text" class="input" id="parts-0-notes" name="parts[0][notes]"
                                    placeholder="{{ __('es. fornitore, codice ricambio') }}">
                            </div>
                            <div class="row2-tight" style="margin-top:10px;">
                                <div class="field">
                                    <label for="parts-0-ordered_at">{{ __('Data ordine (opzionale)') }}</label>
                                    <input type="date" class="input" id="parts-0-ordered_at"
                                        name="parts[0][ordered_at]">
                                </div>
                                <div class="field">
                                    <label for="parts-0-arrived_at">{{ __('Data arrivo (opzionale)') }}</label>
                                    <input type="date" class="input" id="parts-0-arrived_at"
                                        name="parts[0][arrived_at]">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" id="add-spare-part-row" class="btn ghost">{{ __('+ Aggiungi un altro pezzo') }}</button>
                        <button type="submit" class="btn primary">{{ __('Salva pezzi') }}</button>
                    </div>
                </form>
            </details>
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

    @if ($activities->isNotEmpty() || $sparePartActivities->isNotEmpty())
        @php
            $statusEvent = fn($status) => match ($status) {
                'in_progress' => ['label' => __('Preso in carico'), 'color' => 'amber'],
                'closed' => ['label' => __('Risolto'), 'color' => 'green'],
                'open' => ['label' => __('Riaperto'), 'color' => 'red'],
                default => ['label' => __('Aggiornato'), 'color' => 'gray'],
            };
            // "Preso in carico"/"Risolto" scattano quando si completa un
            // appuntamento collegato (MaintenanceCompletionService): la data
            // che racconta il fatto è il rientro/appuntamento, non il
            // timestamp di quando l'operatore ha salvato il completamento in
            // app, spesso successivo. Abbinamento per vicinanza nel tempo
            // (stesso secondo/minuto, stessa richiesta HTTP) tra il log
            // attività e l'appuntamento aggiornato: oltre 30s non è più lo
            // stesso evento, si tiene il timestamp reale come ripiego.
            $linkedRecords = $issue->maintenanceRecordItems()
                ->with('maintenanceRecord')
                ->get()
                ->pluck('maintenanceRecord')
                ->filter();
            $appointmentDateFor = function ($activity) use ($linkedRecords) {
                $closest = $linkedRecords->sortBy(fn($r) => abs($r->updated_at->diffInSeconds($activity->created_at)))->first();
                if (! $closest || abs($closest->updated_at->diffInSeconds($activity->created_at)) > 30) {
                    return null;
                }

                return $closest->return_date ?? $closest->appointment_date;
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
                $date = in_array($newStatus, ['in_progress', 'closed'], true)
                    ? ($appointmentDateFor($activity) ?? $activity->created_at)
                    : $activity->created_at;
                $timelineEntries->push([...$event, 'date' => $date]);
            }
            // Storico pezzi ordinati/arrivati: vive nell'activity log di
            // IssueSparePart, unito qui perché il guasto ha un'unica
            // timeline visibile. Il nome va preso dal pezzo attuale, non
            // da $attrs: un update che tocca solo una data non include
            // "name" tra gli attributi modificati (logOnlyDirty).
            $sparePartNames = $issue->spareParts->pluck('name', 'id');
            // Le date (cast 'date') vengono serializzate in JSON come
            // ISO8601 UTC ("...T23:00:00Z" per mezzanotte locale del
            // giorno dopo, con APP_TIMEZONE=Europe/Rome): va riportata al
            // fuso dell'app prima di leggerne solo la data, altrimenti
            // compare il giorno prima.
            $parseLogDate = fn($value) => \Carbon\Carbon::parse($value)->setTimezone(config('app.timezone'));
            foreach ($sparePartActivities as $activity) {
                $attrs = $activity->attribute_changes?->get('attributes', []) ?? [];
                $old = $activity->attribute_changes?->get('old', []) ?? [];
                $name = $sparePartNames[$activity->subject_id] ?? ($attrs['name'] ?? null);
                if (! $name) {
                    continue;
                }

                if ($activity->event === 'created') {
                    if (! empty($attrs['ordered_at'])) {
                        $timelineEntries->push([
                            'label' => __('Pezzo ordinato: :name', ['name' => $name]),
                            'color' => 'amber',
                            'date' => $parseLogDate($attrs['ordered_at']),
                        ]);
                    }

                    continue;
                }

                if (! empty($attrs['arrived_at']) && empty($old['arrived_at'] ?? null)) {
                    $timelineEntries->push([
                        'label' => __('Pezzo arrivato: :name', ['name' => $name]),
                        'color' => 'green',
                        'date' => $parseLogDate($attrs['arrived_at']),
                    ]);
                }

                if (! empty($attrs['ordered_at']) && empty($old['ordered_at'] ?? null)) {
                    $timelineEntries->push([
                        'label' => __('Pezzo ordinato: :name', ['name' => $name]),
                        'color' => 'amber',
                        'date' => $parseLogDate($attrs['ordered_at']),
                    ]);
                }
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

    <script>
        document.getElementById('add-spare-part-row')?.addEventListener('click', function() {
            const rows = document.getElementById('spare-parts-rows');
            const i = rows.children.length;
            const row = document.createElement('div');
            row.className = 'spare-part-row';
            row.style.cssText = 'border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-bottom:10px;';
            row.innerHTML = `
                <div class="field">
                    <label for="parts-${i}-name">{{ __('Nome pezzo') }}</label>
                    <input type="text" class="input" id="parts-${i}-name" name="parts[${i}][name]" placeholder="{{ __('es. Specchietto destro') }}" required>
                </div>
                <div class="field" style="margin-top:10px;">
                    <label for="parts-${i}-notes">{{ __('Nota (opzionale)') }}</label>
                    <input type="text" class="input" id="parts-${i}-notes" name="parts[${i}][notes]" placeholder="{{ __('es. fornitore, codice ricambio') }}">
                </div>
                <div class="row2-tight" style="margin-top:10px;">
                    <div class="field">
                        <label for="parts-${i}-ordered_at">{{ __('Data ordine (opzionale)') }}</label>
                        <input type="date" class="input" id="parts-${i}-ordered_at" name="parts[${i}][ordered_at]">
                    </div>
                    <div class="field">
                        <label for="parts-${i}-arrived_at">{{ __('Data arrivo (opzionale)') }}</label>
                        <input type="date" class="input" id="parts-${i}-arrived_at" name="parts[${i}][arrived_at]">
                    </div>
                </div>
            `;
            rows.appendChild(row);
        });
    </script>
@endsection
