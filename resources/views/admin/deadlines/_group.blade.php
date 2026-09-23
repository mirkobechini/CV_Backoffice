{{--
    Raggruppamento ricorsivo: $groups può contenere, per ogni etichetta, o
    un'altra collection di sotto-gruppi (raggruppamenti combinati, es.
    veicolo > tipo) o direttamente le scadenze foglia. flatten() conta le
    scadenze effettive indipendentemente dalla profondità di annidamento.
--}}
@php($parentIds = $parentIds ?? [])
@foreach ($groups as $groupLabel => $groupItems)
    {{--
        depth 0 = raggruppamento principale: più stacco visivo dalla riga
        precedente (bordo sopra), per non confondere l'ultima riga di un
        gruppo con l'inizio del successivo. depth > 0 = sotto-gruppo
        annidato (es. tipo dentro veicolo): stile più leggero per restare
        chiaramente distinto dall'intestazione principale sopra di lui.

        $groupId identifica questo gruppo per il collapse lato client (vedi
        resources/js/app.js): non deve essere stabile tra richieste, solo
        univoco nella pagina corrente. $childIds è la catena di antenati
        che le righe/i sotto-gruppi al suo interno portano in data-groups,
        così collassare un gruppo nasconde anche tutto ciò che contiene.
    --}}
    @php($groupId = 'g' . \Illuminate\Support\Str::random(8))
    @php($childIds = [...$parentIds, $groupId])
    <tr class="group-row {{ $depth > 0 ? 'group-row-sub' : '' }}" data-group-row="{{ $groupId }}"
        @if (!empty($parentIds)) data-groups="{{ implode(' ', $parentIds) }}" @endif>
        <td colspan="5" style="padding-left: {{ 12 + $depth * 20 }}px">
            <i class="fa-solid fa-chevron-down group-chevron"></i>{{ $groupLabel }} ({{ $groupItems->flatten()->count() }})
        </td>
    </tr>

    @if ($groupItems->first() instanceof \App\Models\Deadline)
        @foreach ($groupItems as $deadline)
            @include('admin.deadlines._row', ['deadline' => $deadline, 'groupIds' => $childIds])
        @endforeach
    @else
        @include('admin.deadlines._group', ['groups' => $groupItems, 'depth' => $depth + 1, 'parentIds' => $childIds])
    @endif
@endforeach
