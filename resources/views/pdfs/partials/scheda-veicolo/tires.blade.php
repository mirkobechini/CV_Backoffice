<!-- PNEUMATICI -->
<div class="section">
    <div class="section-title">Pneumatici ({{ $vehicle->tires->count() }})</div>
    @if ($vehicle->tires->isEmpty())
        <p class="empty">Nessun pneumatico registrato.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Posizione</th>
                    <th>Stagionalità</th>
                    <th>Marca / Modello</th>
                    <th>Prossimo cambio</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->tires->sortBy('position') as $tire)
                    <tr>
                        <td>{{ $tire->position_label }}</td>
                        <td>{{ $tire->season_label }}</td>
                        <td>{{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: '—' }}</td>
                        <td>{{ $tire->next_change_date_formatted ?? '—' }}</td>
                        <td>
                            <span
                                class="tag tag-{{ match ($tire->status) {
                                    'mounted' => 'green',
                                    'stored' => 'blue',
                                    'retired' => 'red',
                                    default => 'yellow',
                                } }}">{{ $tire->status_label }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
