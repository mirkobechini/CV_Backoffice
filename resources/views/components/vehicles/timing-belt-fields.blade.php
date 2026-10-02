@props(['vehicle' => null, 'sectionNumber'])

@php
    $selected = old('timing_belt_type', $vehicle->timing_belt_type ?? 'chain');
@endphp

<div class="form-section" style="margin-bottom:0;">
    <h2><span class="num">{{ $sectionNumber }}</span> {{ __('Distribuzione') }}
        @if (session('timing_belt_suggested'))
            <span class="badge b-amber" title="{{ __('Stima basata su marca/modello: verifica prima di salvare.') }}">
                <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Suggerito dall\'AI — verifica') }}
            </span>
        @endif
    </h2>
    <div class="row3">
        <label class="check">
            <input type="radio" value="chain" id="timing_belt_type_chain" name="timing_belt_type"
                {{ $selected == 'chain' ? 'checked' : '' }}>
            <div>
                <div class="label">{{ __('Catena') }}</div>
                <div class="sub">{{ __('Nessuna scadenza: la catena non richiede sostituzioni periodiche.') }}</div>
            </div>
        </label>
        <label class="check">
            <input type="radio" value="dry_belt" id="timing_belt_type_dry_belt" name="timing_belt_type"
                {{ $selected == 'dry_belt' ? 'checked' : '' }}>
            <div>
                <div class="label">{{ __('Cinghia a secco') }}</div>
                <div class="sub">{{ __('Genera una scadenza "Cinghia Distribuzione" dopo 100.000 km, nessun limite di tempo.') }}</div>
            </div>
        </label>
        <label class="check">
            <input type="radio" value="oil_bath_belt" id="timing_belt_type_oil_bath_belt" name="timing_belt_type"
                {{ $selected == 'oil_bath_belt' ? 'checked' : '' }}>
            <div>
                <div class="label">{{ __("Cinghia a bagno d'olio") }}</div>
                <div class="sub">
                    {{ __('Genera una scadenza "Cinghia Distribuzione" dopo 100.000 km o 10 anni.') }}
                </div>
            </div>
        </label>
    </div>
</div>
