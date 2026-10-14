<!-- SCADENZE -->
<div class="section">
    <div class="section-title">Scadenze ({{ $vehicle->deadlines->count() }})</div>
    @if ($vehicle->deadlines->isEmpty())
        <p class="empty">Nessuna scadenza registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->deadlines as $deadline)
                    <tr>
                        <td>{{ $deadline->type }}</td>
                        <td><strong>{{ $deadline->due_date_formatted ?? $deadline->km_remaining_label ?? '—' }}</strong>
                        </td>
                        <td><span class="tag tag-{{ $deadline->status_color }}">{{ $deadline->status_label }}</span>
                        </td>
                    </tr>
                    @if ($deadline->type === \App\Models\Deadline::TYPE_ASSICURAZIONE && $deadline->insuranceCoverages->isNotEmpty())
                        <tr>
                            <td colspan="3" style="padding-left:16px;">
                                {{ $deadline->insurance_company ?? '—' }}
                                @if ($deadline->insurance_policy_number)
                                    · Polizza {{ $deadline->insurance_policy_number }}
                                @endif
                                <br>
                                @foreach ($deadline->insuranceCoverages as $coverage)
                                    {{ $coverage->coverage_type }}: €
                                    {{ number_format((float) $coverage->cost, 2, ',', '.') }}@if (! $loop->last)
                                        ,
                                    @endif
                                @endforeach
                                — Totale: € {{ number_format((float) $deadline->insurance_premium_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
</div>
