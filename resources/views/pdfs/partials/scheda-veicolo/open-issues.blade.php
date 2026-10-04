<!-- GUASTI APERTI -->
<div class="section">
    <div class="section-title">Guasti Aperti ({{ $vehicle->open_issues->count() }})</div>
    @if ($vehicle->open_issues->isEmpty())
        <p class="empty">Nessun guasto aperto.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Descrizione</th>
                    <th>Data</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->open_issues as $issue)
                    <tr>
                        <td>{{ $issue->description }}</td>
                        <td>{{ $issue->event_date?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="tag tag-{{ $issue->status_color }}">{{ $issue->status_label }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
