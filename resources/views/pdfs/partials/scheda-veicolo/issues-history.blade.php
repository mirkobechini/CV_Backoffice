<!-- STORICO GUASTI -->
<div class="section">
    <div class="section-title">Storico Guasti e Interventi ({{ $vehicle->issues->count() }})</div>
    @if ($vehicle->issues->isEmpty())
        <p class="empty">Nessun guasto registrato.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Guasto</th>
                    <th>Data</th>
                    <th>Intervento</th>
                    <th>Data Intervento</th>
                    <th>Officina</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->issues as $issue)
                    <tr>
                        <td>{{ $issue->description }}</td>
                        <td>{{ $issue->event_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->activity_type ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->appointment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->provider?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
