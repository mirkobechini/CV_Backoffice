{{--
    Raggruppamento ricorsivo: $groups può contenere, per ogni etichetta, o
    un'altra collection di sotto-gruppi (raggruppamenti combinati, es.
    veicolo > tipo) o direttamente le scadenze foglia. flatten() conta le
    scadenze effettive indipendentemente dalla profondità di annidamento.
--}}
@foreach ($groups as $groupLabel => $groupItems)
    <tr class="group-row">
        <td colspan="5" style="padding-left: {{ $depth * 20 }}px">
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
