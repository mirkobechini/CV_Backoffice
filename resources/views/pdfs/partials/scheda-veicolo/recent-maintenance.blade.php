<!-- MANUTENZIONI RECENTI -->
<div class="section">
    <div class="section-title">Ultime Manutenzioni ({{ $vehicle->maintenanceRecords->count() }})</div>
    @if ($vehicle->maintenanceRecords->isEmpty())
        <p class="empty">Nessuna manutenzione registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Officina</th>
                    <th>Intervento</th>
                    <th>Costo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->maintenanceRecords as $record)
                    <tr>
                        <td>{{ $record->appointment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $record->provider?->name ?? '—' }}</td>
                        <td>{{ $record->items->where('itemable_type', 'App\Models\Issue')->map(fn($item) => $item->itemable?->description)->filter()->implode(', ') ?: $record->activity_type }}
                        </td>
                        <td>{{ $record->cost !== null ? '€ ' . number_format((float) $record->cost, 2, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
