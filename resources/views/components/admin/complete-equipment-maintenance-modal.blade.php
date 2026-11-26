@props(['equipmentMaintenanceRecord', 'disabledReason' => null])

@php
    $routeParameterValue = $equipmentMaintenanceRecord?->getRouteKey();
    $modalIdSuffix = $routeParameterValue ?? 'missing-equipment-maintenance';
    $linkedIssues = $equipmentMaintenanceRecord?->issues ?? collect();
    $linkedEquipments = $equipmentMaintenanceRecord?->equipments ?? collect();
    // La selezione "su quali attrezzature è stato fatto l'intervento" ha un
    // effetto concreto (far ripartire la scadenza revisione/collaudo) solo
    // per questi due tipi di appuntamento: per gli altri non c'è nulla da
    // aggiornare sull'attrezzatura, quindi la lista non serve a nulla.
    $showEquipmentCompletionList = $linkedEquipments->count() > 1
        && in_array($equipmentMaintenanceRecord?->activity_type, [
            \App\Models\EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
            \App\Models\EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ], true);
@endphp

@if ($disabledReason)
    <button type="button" class="btn success" disabled title="{{ $disabledReason }}">
        <i class="fa-solid fa-check"></i> Completato
    </button>
@elseif ($routeParameterValue)
    <button type="button" class="btn success" data-bs-toggle="modal"
        data-bs-target="#completeEquipmentMaintenanceModal-{{ $modalIdSuffix }}">
        <i class="fa-solid fa-check"></i> Completato
    </button>

    <div class="modal fade" id="completeEquipmentMaintenanceModal-{{ $modalIdSuffix }}" tabindex="-1"
        aria-labelledby="completeEquipmentMaintenanceModalLabel-{{ $modalIdSuffix }}" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('admin.equipment-maintenance-records.complete', $routeParameterValue) }}"
                    method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="modal-header">
                        <h5 class="modal-title" id="completeEquipmentMaintenanceModalLabel-{{ $modalIdSuffix }}">
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

                        @if ($showEquipmentCompletionList)
                            <p class="mb-2 mt-3"><strong>Su quali attrezzature è stato effettuato l'intervento?</strong></p>
                            <p class="text-muted small mb-2">Le attrezzature non selezionate resteranno collegate all'appuntamento ma senza alcuna revisione/collaudo registrato (da riprogrammare).</p>

                            @foreach ($linkedEquipments as $equipment)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="completed_equipment_ids[]"
                                        id="completed_equipment_{{ $equipment->id }}_{{ $modalIdSuffix }}"
                                        value="{{ $equipment->id }}" checked>
                                    <label class="form-check-label"
                                        for="completed_equipment_{{ $equipment->id }}_{{ $modalIdSuffix }}">
                                        {{ $equipment->name }}
                                        @if ($equipment->serial_number)
                                            — {{ __('matricola') }} {{ $equipment->serial_number }}
                                        @endif
                                        @if ($equipment->vehicle)
                                            — {{ $equipment->vehicle->internal_code }}
                                        @endif
                                    </label>
                                </div>
                            @endforeach
                        @else
                            @foreach ($linkedEquipments as $equipment)
                                <input type="hidden" name="completed_equipment_ids[]" value="{{ $equipment->id }}">
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
