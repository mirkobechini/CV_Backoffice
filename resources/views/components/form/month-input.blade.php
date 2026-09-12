@props(['name', 'label', 'model' => null, 'field' => null, 'value' => null, 'id' => null, 'required' => false])

@php
    $inputId = $id ?? $name;
    $modelField = $field ?? $name;
    $fallbackValue = $value ?? ($model ? data_get($model, $modelField) : null);
    $rawValue = old($name, $fallbackValue);

    if ($rawValue instanceof \DateTimeInterface) {
        $inputValue = $rawValue->format('Y-m');
    } elseif (is_string($rawValue) && preg_match('/^\d{4}-\d{2}/', $rawValue)) {
        $inputValue = substr($rawValue, 0, 7);
    } else {
        $inputValue = $rawValue ?? '';
    }
@endphp

<div class="field">
    <label for="{{ $inputId }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    <input type="text" class="flatpickr-month-input" id="{{ $inputId }}" name="{{ $name }}" value="{{ $inputValue }}"
        placeholder="mm/aaaa" autocomplete="off" data-alt-class="input @error($name) is-invalid @enderror"
        @if ($required) required @endif>
    @error($name)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
