@props(['color'])

@php
    $class = match ($color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        'blue' => 'b-blue',
        'purple' => 'b-purple',
        default => 'b-gray',
    };
@endphp

<span class="badge {{ $class }}">{{ $slot }}</span>
