@props(['tireChanges'])

@if ($tireChanges->isNotEmpty())
    <div class="table-card" style="margin-top:16px;">
        <div class="toolbar">
            <h2>{{ __('Storico cambi gomme') }}</h2>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Data') }}</th>
                        <th>{{ __('Gomma smontata') }}</th>
                        <th>{{ __('Gomma montata') }}</th>
                        <th>{{ __('Km') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tireChanges->sortByDesc('changed_date') as $change)
                        <tr>
                            <td>{{ $change->changed_date_formatted ?? 'N/A' }}</td>
                            <td>
                                @if ($change->previousTire)
                                    {{ $change->previousTire->position_label }} ·
                                    {{ $change->previousTire->season_label }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($change->tire)
                                    {{ $change->tire->position_label }} · {{ $change->tire->season_label }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $change->mileage_at_change ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
