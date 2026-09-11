@props(['items'])

<nav class="breadcrumb" aria-label="breadcrumb">
    @foreach ($items as $item)
        @if (!$loop->first)
            <span class="breadcrumb-sep">/</span>
        @endif
        @if ($loop->last)
            <b>{{ $item['label'] }}</b>
        @elseif (!empty($item['url']))
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @else
            {{ $item['label'] }}
        @endif
    @endforeach
</nav>
