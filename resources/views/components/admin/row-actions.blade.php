@props(['showUrl' => null, 'editUrl' => null, 'deleteTarget' => null])

<div class="row-actions">
    @if ($showUrl)
        <a href="{{ $showUrl }}" class="mini-btn" title="{{ __('Visualizza') }}" aria-label="{{ __('Visualizza') }}"><i
                class="fa-solid fa-eye"></i></a>
    @endif
    @if ($editUrl)
        <a href="{{ $editUrl }}" class="mini-btn" title="{{ __('Modifica') }}" aria-label="{{ __('Modifica') }}"><i
                class="fa-solid fa-pen"></i></a>
    @endif
    {{ $slot ?? '' }}
    @if ($deleteTarget)
        <button type="button" class="mini-btn" title="{{ __('Elimina') }}" aria-label="{{ __('Elimina') }}"
            data-bs-toggle="modal" data-bs-target="{{ $deleteTarget }}"><i class="fa-solid fa-trash"></i></button>
    @endif
</div>
