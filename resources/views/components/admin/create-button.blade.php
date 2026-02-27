@props(['href', 'label' => 'Nuovo'])

<a class="btn btn-success rounded-pill py-0 px-2" href="{{ $href }}" aria-label="Crea {{ $label }}">
    <i class="fa-solid fa-add text-light"></i>
</a>
