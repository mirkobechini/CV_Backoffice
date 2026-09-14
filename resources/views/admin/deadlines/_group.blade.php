{{--
    Raggruppamento ricorsivo: $groups può contenere, per ogni etichetta, o
    un'altra collection di sotto-gruppi (raggruppamenti combinati, es.
    veicolo > tipo) o direttamente le scadenze foglia. flatten() conta le
    scadenze effettive indipendentemente dalla profondità di annidamento.
--}}
@foreach ($groups as $groupLabel => $groupItems)
    {{--
        depth 0 = raggruppamento principale: più stacco visivo dalla riga
        precedente (bordo sopra), per non confondere l'ultima riga di un
        gruppo con l'inizio del successivo. depth > 0 = sotto-gruppo
        annidato (es. tipo dentro veicolo): stile più leggero per restare
        chiaramente distinto dall'intestazione principale sopra di lui.
    --}}
    <tr class="group-row {{ $depth > 0 ? 'group-row-sub' : '' }}">
        <td colspan="5" style="padding-left: {{ 12 + $depth * 20 }}px">
            {{ $groupLabel }} ({{ $groupItems->flatten()->count() }})
        </td>
    </tr>

    @if ($groupItems->first() instanceof \App\Models\Deadline)
        @foreach ($groupItems as $deadline)
            @include('admin.deadlines._row', ['deadline' => $deadline])
        @endforeach
    @else
        @include('admin.deadlines._group', ['groups' => $groupItems, 'depth' => $depth + 1])
    @endif
@endforeach
