@props(['items'])

{{-- Molte pagine indice non hanno un <h1> visibile, solo un <h2> nella
toolbar sotto: questo rompe la gerarchia dei titoli per chi naviga con
uno screen reader (audit 2026-10-19). L'ultima voce del breadcrumb è
sempre il nome della pagina corrente, quindi basta per un <h1> nascosto
visivamente ma letto normalmente. --}}
<h1 class="sr-only">{{ end($items)['label'] }}</h1>
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
