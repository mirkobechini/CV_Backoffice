<!-- STORICO CAMBI GOMME -->
@if ($vehicle->tireChanges->isNotEmpty())
    <div class="section">
        <div class="section-title">Storico Cambi Gomme ({{ $vehicle->tireChanges->count() }})</div>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Gomma smontata</th>
                    <th>Gomma montata</th>
                    <th>Km</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->tireChanges->sortByDesc('changed_date') as $change)
                    <tr>
                        <td>{{ $change->changed_date_formatted ?? '—' }}</td>
                        <td>
                            {{ $change->previousTire ? $change->previousTire->position_label.' · '.$change->previousTire->season_label : '—' }}
                        </td>
                        <td>
                            {{ $change->tire ? $change->tire->position_label.' · '.$change->tire->season_label : '—' }}
                        </td>
                        <td>{{ $change->mileage_at_change ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
