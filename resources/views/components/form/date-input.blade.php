@props(['name', 'label', 'model' => null, 'field' => null, 'value' => null, 'id' => null, 'required' => false])

@php
    $inputId = $id ?? $name;
    $modelField = $field ?? $name;
    $fallbackValue = $value ?? ($model ? data_get($model, $modelField) : null);
    $rawValue = old($name, $fallbackValue);

    if ($rawValue instanceof \DateTimeInterface) {
        $inputValue = $rawValue->format('Y-m-d');
    } elseif (is_string($rawValue) && preg_match('/^\d{4}-\d{2}-\d{2}/', $rawValue)) {
        $inputValue = substr($rawValue, 0, 10);
    } else {
        $inputValue = $rawValue ?? '';
    }
@endphp

<div class="mb-3">
    <label for="{{ $inputId }}" class="form-label">{{ $label }}</label>
    <input type="date" class="form-control @error($name) is-invalid @enderror" id="{{ $inputId }}"
        name="{{ $name }}" value="{{ $inputValue }}" @if ($required) required @endif>
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
