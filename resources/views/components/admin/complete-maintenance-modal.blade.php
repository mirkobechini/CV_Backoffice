@props(['maintenanceRecord', 'disabledReason' => null])

@php
    $routeParameterValue = $maintenanceRecord?->getRouteKey();
    $modalIdSuffix = $routeParameterValue ?? 'missing-maintenance';
    // Una domanda per ogni gomma che ha davvero qualcosa da sostituire
    // (primo montaggio in una posizione = nessuna gomma precedente =
    // nessuna scelta da fare per quella), con l'identità della gomma
    // sostituita così l'utente sa a cosa si riferisce ogni scelta.
    $completionService = app(\App\Services\MaintenanceCompletionService::class);
    $tireDispositionChoices = $maintenanceRecord
        ? $completionService->tireItemsRequiringDisposition($maintenanceRecord)
            ->map(fn ($item) => [
                'item' => $item,
                'previousTire' => app(\App\Services\TireChangeService::class)->findPreviousMountedTire($item->itemable),
            ])
        : collect();
    // Un sì/no indipendente per ciascun guasto/scadenza collegati, non più
    // un'unica scelta valida per tutti (bug segnalato nell'audit 2026-10-19).
    $linkedIssues = $maintenanceRecord ? $completionService->linkedIssues($maintenanceRecord) : collect();
    $linkedDeadlines = $maintenanceRecord ? $completionService->linkedDeadlines($maintenanceRecord) : collect();
    $isTimingBeltAppointment = $maintenanceRecord?->activity_type === \App\Models\MaintenanceRecord::ACTIVITY_TIMING_BELT;
    $tirePositionLabels = [
        'front_left' => 'Anteriore sinistra',
        'front_right' => 'Anteriore destra',
        'rear_left' => 'Posteriore sinistra',
        'rear_right' => 'Posteriore destra',
    ];
@endphp

@if ($disabledReason)
    <button type="button" class="btn success" disabled title="{{ $disabledReason }}">
        <i class="fa-solid fa-check"></i> Completato
    </button>
@elseif ($routeParameterValue)
    <button type="button" class="btn success" data-bs-toggle="modal"
        data-bs-target="#completeMaintenanceModal-{{ $modalIdSuffix }}">
        <i class="fa-solid fa-check"></i> Completato
    </button>

    <div class="modal fade" id="completeMaintenanceModal-{{ $modalIdSuffix }}" tabindex="-1"
        aria-labelledby="completeMaintenanceModalLabel-{{ $modalIdSuffix }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.maintenance-records.complete', $routeParameterValue) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="modal-header">
                        <h5 class="modal-title" id="completeMaintenanceModalLabel-{{ $modalIdSuffix }}">
                            Completa intervento
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-3">Confermi il completamento in data odierna?</p>

                        @foreach ($linkedIssues as $issue)
                            <p class="mb-2 mt-3"><strong>Il guasto "{{ $issue->description }}" è stato aggiustato?</strong></p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resolved_issues[{{ $issue->id }}]"
                                    id="resolved_issue_{{ $issue->id }}_yes_{{ $modalIdSuffix }}" value="1" required>
                                <label class="form-check-label" for="resolved_issue_{{ $issue->id }}_yes_{{ $modalIdSuffix }}">
                                    Sì
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resolved_issues[{{ $issue->id }}]"
                                    id="resolved_issue_{{ $issue->id }}_no_{{ $modalIdSuffix }}" value="0" required>
                                <label class="form-check-label" for="resolved_issue_{{ $issue->id }}_no_{{ $modalIdSuffix }}">
                                    No
                                </label>
                            </div>
                        @endforeach

                        @foreach ($linkedDeadlines as $deadline)
                            <p class="mb-2 mt-3"><strong>La scadenza "{{ $deadline->type }}" è stata rinnovata?</strong></p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resolved_deadlines[{{ $deadline->id }}]"
                                    id="resolved_deadline_{{ $deadline->id }}_yes_{{ $modalIdSuffix }}" value="1" required>
                                <label class="form-check-label" for="resolved_deadline_{{ $deadline->id }}_yes_{{ $modalIdSuffix }}">
                                    Sì
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="resolved_deadlines[{{ $deadline->id }}]"
                                    id="resolved_deadline_{{ $deadline->id }}_no_{{ $modalIdSuffix }}" value="0" required>
                                <label class="form-check-label" for="resolved_deadline_{{ $deadline->id }}_no_{{ $modalIdSuffix }}">
                                    No
                                </label>
                            </div>
                        @endforeach

                        @if ($isTimingBeltAppointment)
                            <p class="mb-2 mt-3"><strong>La cinghia di distribuzione è stata sostituita?</strong></p>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="timing_belt_completed"
                                    id="timing_belt_completed_yes_{{ $modalIdSuffix }}" value="1" required>
                                <label class="form-check-label" for="timing_belt_completed_yes_{{ $modalIdSuffix }}">
                                    Sì
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="timing_belt_completed"
                                    id="timing_belt_completed_no_{{ $modalIdSuffix }}" value="0" required>
                                <label class="form-check-label" for="timing_belt_completed_no_{{ $modalIdSuffix }}">
                                    No
                                </label>
                            </div>
                        @endif

                        @if ($tireDispositionChoices->isNotEmpty())
                            <p class="mb-2 mt-3"><strong>Le gomme sostituite:</strong></p>

                            @foreach ($tireDispositionChoices as $choice)
                                @php
                                    $tireKey = $choice['item']->itemable_id;
                                    $previousTire = $choice['previousTire'];
                                    $positionLabel = $tirePositionLabels[$previousTire->position] ?? $previousTire->position;
                                    $previousTireLabel = trim(($previousTire->brand ?? '') . ' ' . ($previousTire->model_name ?? '')) ?: ($previousTire->size ?? '');
                                @endphp
                                <p class="mb-1 mt-2">
                                    <strong>{{ $positionLabel }}</strong>
                                    @if ($previousTireLabel)
                                        — {{ $previousTireLabel }}
                                    @endif
                                </p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                        name="previous_disposition[{{ $tireKey }}]"
                                        id="previous_disposition_{{ $tireKey }}_stored_{{ $modalIdSuffix }}"
                                        value="stored" required>
                                    <label class="form-check-label"
                                        for="previous_disposition_{{ $tireKey }}_stored_{{ $modalIdSuffix }}">
                                        Va in magazzino
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio"
                                        name="previous_disposition[{{ $tireKey }}]"
                                        id="previous_disposition_{{ $tireKey }}_retired_{{ $modalIdSuffix }}"
                                        value="retired" required>
                                    <label class="form-check-label"
                                        for="previous_disposition_{{ $tireKey }}_retired_{{ $modalIdSuffix }}">
                                        Viene dismessa
                                    </label>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                        <button type="submit" class="btn btn-success">Conferma</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@else
    <button type="button" class="btn success" disabled>Completato</button>
@endif
