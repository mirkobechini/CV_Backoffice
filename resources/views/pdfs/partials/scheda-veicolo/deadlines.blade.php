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
                        <td><strong>
                                @if ($deadline->due_date)
                                    {{ $deadline->due_date->format('d/m/Y') }}
                                @elseif ($deadline->km_remaining_label)
                                    {{ $deadline->km_remaining_label }}
                                @else
                                    —
                                @endif
                            </strong></td>
                        <td><span class="tag tag-{{ $deadline->status_color }}">{{ $deadline->status_label }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
