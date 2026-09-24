@props(['name', 'label', 'model' => null, 'field' => null, 'value' => null, 'id' => null, 'required' => false])

@php
    $inputId = $id ?? $name;
    $modelField = $field ?? $name;
    $fallbackValue = $value ?? ($model ? data_get($model, $modelField) : null);
    $rawValue = old($name, $fallbackValue);
@endphp

<div class="field tire-size-input" data-tire-size-for="{{ $inputId }}">
    <label for="{{ $inputId }}-width">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    <div class="tire-size-fields">
        <input type="text" inputmode="numeric" class="input tire-size-width" id="{{ $inputId }}-width"
            placeholder="{{ __('es. 225') }}" maxlength="3" aria-label="{{ __('Larghezza (mm)') }}">
        <span class="tire-size-sep">/</span>
        <input type="text" inputmode="numeric" class="input tire-size-ratio" placeholder="{{ __('es. 75') }}"
            maxlength="3" aria-label="{{ __('Profilo (%)') }}">
        <span class="tire-size-sep">R</span>
        <input type="text" inputmode="numeric" class="input tire-size-rim" placeholder="{{ __('es. 16') }}"
            maxlength="2" aria-label="{{ __('Diametro cerchio (pollici)') }}">
        <label class="tire-size-reinforced-label" title="{{ __('Rinforzato / commerciale') }}">
            <input type="checkbox" class="tire-size-reinforced"> C
        </label>
        <input type="text" class="input tire-size-index" placeholder="{{ __('es. 121/120Q') }}" maxlength="10"
            aria-label="{{ __('Indice di carico/velocità (facoltativo)') }}">
    </div>
    <input type="hidden" id="{{ $inputId }}" name="{{ $name }}" value="{{ $rawValue }}">
    @error($name)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
