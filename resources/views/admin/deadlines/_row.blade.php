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
                <x-admin.status-badge :color="$deadline->status_color">{{ $deadline->date_remaining_label }}</x-admin.status-badge>
            @endif
            @if ($deadline->km_remaining_label)
                <x-admin.status-badge :color="$deadline->status_color">{{ $deadline->km_remaining_label }}</x-admin.status-badge>
            @endif
            @if (!$deadline->date_remaining_label && !$deadline->km_remaining_label)
                <span class="badge b-gray">—</span>
            @endif
        </div>
    </td>
    <td><x-admin.status-badge :color="$deadline->status_color">{{ $deadline->status_label }}</x-admin.status-badge></td>
    <td class="code">{{ $deadline->vehicle->internal_code ?? 'N/A' }}</td>
    <td>
        <x-admin.row-actions
            :show-url="route('admin.deadlines.show', $deadline->id)"
            :edit-url="route('admin.deadlines.edit', $deadline->id)"
            :delete-target="'#confirmDeleteModal-' . $deadline->id" />
    </td>
</tr>
<x-admin.delete-modal type="deadline" :object="$deadline" />
