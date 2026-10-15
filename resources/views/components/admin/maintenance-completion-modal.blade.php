@props(['tireDispositionChoices' => null])

@php
    $tireDispositionChoices ??= collect();
    $tirePositionLabels = [
        'front_left' => 'Anteriore sinistra',
        'front_right' => 'Anteriore destra',
        'rear_left' => 'Posteriore sinistra',
        'rear_right' => 'Posteriore destra',
    ];
@endphp

{{-- Dialog completamento: appare quando la data di rientro è compilata --}}
<div class="modal fade" id="completionModal" tabindex="-1" aria-labelledby="completionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completionModalLabel">{{ __('Completamento appuntamento') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <p class="hint" style="margin-bottom:12px;">
                    {{ __('Segna quali guasti e scadenze sono stati completati in questo appuntamento.') }}</p>
                <div id="completion-issues" style="margin-bottom:12px;">
                    <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Guasti') }}</h6>
                    <div id="completion-issues-list"></div>
                </div>
                <div id="completion-deadlines" style="margin-bottom:12px;">
                    <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Scadenze') }}</h6>
                    <div id="completion-deadlines-list"></div>
                </div>
                @if ($tireDispositionChoices->isNotEmpty())
                    <div id="completion-tires" style="margin-bottom:12px;">
                        <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Gomme sostituite') }}</h6>
                        <div id="tire-disposition-error" class="field-error" style="display:none; margin-bottom:8px;">
                            {{ __('Indica cosa fare di ogni gomma sostituita.') }}
                        </div>
                        @error('previous_disposition')
                            <div class="field-error" style="margin-bottom:8px;">{{ $message }}</div>
                        @enderror
                        @foreach ($tireDispositionChoices as $choice)
                            @php
                                $tireKey = $choice['item']->itemable_id;
                                $previousTire = $choice['previousTire'];
                                $positionLabel = $tirePositionLabels[$previousTire->position] ?? $previousTire->position;
                                $previousTireLabel = trim(($previousTire->brand ?? '').' '.($previousTire->model_name ?? '')) ?: ($previousTire->size ?? '');
                            @endphp
                            <div class="field" style="margin-bottom:10px;">
                                <label style="font-weight:600;">
                                    {{ $positionLabel }}@if ($previousTireLabel) — {{ $previousTireLabel }} @endif
                                </label>
                                <div class="form-check">
                                    <input class="form-check-input completion-tire-disposition" type="radio"
                                        data-tire-id="{{ $tireKey }}" name="comp_tire_disposition_{{ $tireKey }}"
                                        id="comp_tire_{{ $tireKey }}_stored" value="stored">
                                    <label class="form-check-label" for="comp_tire_{{ $tireKey }}_stored">
                                        {{ __('Va in magazzino') }}
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input completion-tire-disposition" type="radio"
                                        data-tire-id="{{ $tireKey }}" name="comp_tire_disposition_{{ $tireKey }}"
                                        id="comp_tire_{{ $tireKey }}_retired" value="retired">
                                    <label class="form-check-label" for="comp_tire_{{ $tireKey }}_retired">
                                        {{ __('Viene dismessa') }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                <button type="button" class="btn btn-success" id="completion-confirm-btn">{{ __('Conferma') }}</button>
            </div>
        </div>
    </div>
</div>
