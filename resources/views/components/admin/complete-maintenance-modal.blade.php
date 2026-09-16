@props(['maintenanceRecord', 'disabledReason' => null])

@php
    $routeParameterValue = $maintenanceRecord?->getRouteKey();
    $modalIdSuffix = $routeParameterValue ?? 'missing-maintenance';
    $tireItem = $maintenanceRecord?->items
        ?->first(fn ($item) => $item->itemable_type === \App\Models\Tire::class);
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
                        <p class="mb-2"><strong>Il guasto è stato aggiustato?</strong></p>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="issue_resolved"
                                id="issue_resolved_yes_{{ $modalIdSuffix }}" value="1" required>
                            <label class="form-check-label" for="issue_resolved_yes_{{ $modalIdSuffix }}">
                                Sì
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="issue_resolved"
                                id="issue_resolved_no_{{ $modalIdSuffix }}" value="0" required>
                            <label class="form-check-label" for="issue_resolved_no_{{ $modalIdSuffix }}">
                                No
                            </label>
                        </div>

                        @if ($tireItem)
                            <p class="mb-2 mt-3"><strong>Le gomme sostituite:</strong></p>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="previous_disposition"
                                    id="previous_disposition_stored_{{ $modalIdSuffix }}" value="stored" required>
                                <label class="form-check-label" for="previous_disposition_stored_{{ $modalIdSuffix }}">
                                    Vanno in magazzino
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="previous_disposition"
                                    id="previous_disposition_retired_{{ $modalIdSuffix }}" value="retired" required>
                                <label class="form-check-label" for="previous_disposition_retired_{{ $modalIdSuffix }}">
                                    Vengono dismesse
                                </label>
                            </div>
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
