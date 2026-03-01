@props(['showUrl', 'editUrl', 'deleteTarget', 'label'])

@php
    $showBackUrl = $showUrl . (str_contains($showUrl, '?') ? '&' : '?') . 'back=' . urlencode(url()->full());
@endphp

<td class="text-nowrap">
    <a href="{{ $showBackUrl }}" class="btn btn-primary" aria-label="Visualizza {{ $label }}">Visualizza</a>
    <a href="{{ $editUrl }}" class="btn btn-warning" aria-label="Modifica {{ $label }}">Modifica</a>
    <button type="button" data-bs-toggle="modal" data-bs-target="{{ $deleteTarget }}" class="btn btn-danger"
        aria-label="Elimina {{ $label }}">Elimina</button>
</td>
