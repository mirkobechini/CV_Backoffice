@props(['href', 'label' => 'Nuovo'])

@php
    $createBackUrl = $href . (str_contains($href, '?') ? '&' : '?') . 'back=' . urlencode(url()->full());
@endphp

<a class="btn btn-success rounded-pill py-0 px-2" href="{{ $createBackUrl }}" aria-label="Crea {{ $label }}">
    <i class="fa-solid fa-add text-light"></i>
</a>
