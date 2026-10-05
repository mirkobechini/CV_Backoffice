<!-- EQUIPAGGIAMENTO -->
<div class="section">
    <div class="section-title">Dotazioni di Bordo ({{ $vehicle->equipment->count() }})</div>
    @if ($vehicle->equipment->isEmpty())
        <p class="empty">Nessuna dotazione registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Matricola</th>
                    <th>Revisione</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->equipment as $equipment)
                    <tr>
                        <td>{{ $equipment->name }}</td>
                        <td>{{ $equipment->serial_number }}</td>
                        <td>{{ $equipment->revision_date ? $equipment->revision_date->format('d/m/Y') : '—' }}</td>
                        <td><span class="tag tag-{{ $equipment->status_color }}">{{ $equipment->status_label }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
