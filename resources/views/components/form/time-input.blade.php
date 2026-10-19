@props(['name', 'label', 'value' => null, 'id' => null, 'required' => false])

@php
    $inputId = $id ?? $name;
    $rawValue = old($name, $value);
@endphp

<div class="field">
    <label for="{{ $inputId }}">{{ $label }} @if ($required)<span class="req">*</span>@endif</label>
    <input type="time" class="input @error($name) is-invalid @enderror" id="{{ $inputId }}" name="{{ $name }}"
        value="{{ $rawValue }}" aria-describedby="{{ $inputId }}-error" @if ($required) required @endif>
    @error($name)
        <div class="field-error" id="{{ $inputId }}-error">{{ $message }}</div>
    @enderror
</div>
