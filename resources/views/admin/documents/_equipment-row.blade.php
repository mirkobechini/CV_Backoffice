@php
    $badgeClass = fn ($color) => match ($color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

<tr @if ($groupKey !== null) data-groups="g{{ $groupKey }}" @endif>
    <td>
        <a href="{{ route('admin.equipments.show', $item) }}">
            {{ $item->name ?? $item->equipmentType?->name ?? 'N/A' }}
        </a>
    </td>
    <td class="code">{{ $item->vehicle?->internal_code ?? 'N/A' }}</td>
    <td><span class="badge {{ $badgeClass($item->status_color) }}">{{ $item->status_label }}</span></td>
    <td><span class="badge {{ $badgeClass($item->collaudo_status_color) }}">{{ $item->collaudo_status_label }}</span></td>
</tr>
