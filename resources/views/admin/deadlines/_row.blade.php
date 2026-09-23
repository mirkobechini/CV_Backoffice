@php
    $badgeClass = fn($color) => match ($color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

<tr @if (!empty($groupIds)) data-groups="{{ implode(' ', $groupIds) }}" @endif>
    <td>
        <div class="type-cell">
            <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
            <span class="type-name">{{ $deadline->type }}</span>
        </div>
    </td>
    <td>
        <div class="dl-badges">
            @if ($deadline->date_remaining_label)
                <span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->date_remaining_label }}</span>
            @endif
            @if ($deadline->km_remaining_label)
                <span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->km_remaining_label }}</span>
            @endif
            @if (!$deadline->date_remaining_label && !$deadline->km_remaining_label)
                <span class="badge b-gray">—</span>
            @endif
        </div>
    </td>
    <td><span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->status_label }}</span></td>
    <td class="code">{{ $deadline->vehicle->internal_code ?? 'N/A' }}</td>
    <td>
        <div class="row-actions">
            <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
            <a href="{{ route('admin.deadlines.edit', $deadline->id) }}" class="mini-btn"
                title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
            <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $deadline->id }}"><i class="fa-solid fa-trash"></i></button>
        </div>
    </td>
</tr>
<x-admin.delete-modal type="deadline" :object="$deadline" />
