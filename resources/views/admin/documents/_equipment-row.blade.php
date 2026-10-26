<tr @if ($groupKey !== null) data-groups="g{{ $groupKey }}" @endif>
    <td>
        <a href="{{ route('admin.equipments.show', $item) }}">
            {{ $item->name ?? $item->equipmentType?->name ?? 'N/A' }}
        </a>
    </td>
    <td class="code">{{ $item->vehicle?->internal_code ?? 'N/A' }}</td>
    <td><x-admin.status-badge :color="$item->status_color">{{ $item->status_label }}</x-admin.status-badge></td>
    <td><x-admin.status-badge :color="$item->collaudo_status_color">{{ $item->collaudo_status_label }}</x-admin.status-badge></td>
</tr>
