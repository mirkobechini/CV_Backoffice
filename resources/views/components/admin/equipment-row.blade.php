@props(['equipment', 'groupKey' => null])

@php
    $statusBadgeClass = match ($equipment->overall_status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
@endphp

<tr @if ($groupKey !== null) data-groups="g{{ $groupKey }}" @endif>
    <td><input type="checkbox" class="equipment-select" value="{{ $equipment->id }}"></td>
    <td>
        <div class="vin">
            <span class="thumb t{{ ($equipment->id % 5) + 1 }}"><i
                    class="fa-solid fa-fire-extinguisher"></i></span>
            <div class="vin-name">{{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
            </div>
        </div>
    </td>
    <td>{{ $equipment->equipmentType->name ?? 'N/A' }}</td>
    <td class="code">{{ $equipment->serial_number ?: '—' }}</td>
    <td>
        {{ $equipment->expiration_date_formatted ?? '—' }}
        @if ($equipment->expiration_date)
            <div class="cell-sub">
                @php($daysDiff = \Carbon\Carbon::today()->diffInDays($equipment->expiration_date, false))
                @if ($daysDiff < 0)
                    {{ __('scaduta da :n gg', ['n' => abs($daysDiff)]) }}
                @else
                    {{ __('scade tra :n gg', ['n' => $daysDiff]) }}
                @endif
            </div>
        @endif
        {{-- Il collaudo riguarda solo alcuni tipi di attrezzatura (es.
        estintori): niente colonna dedicata sempre vuota per tutto il
        resto, solo un avviso qui quando è vicino o scaduto. --}}
        @if (in_array($equipment->collaudo_status_label, ['In scadenza', 'Scaduto'], true))
            <div class="{{ $equipment->collaudo_status_label === 'Scaduto' ? 'cell-sub-expired' : 'cell-sub-due' }}">
                @php($collaudoDaysDiff = \Carbon\Carbon::today()->diffInDays($equipment->next_collaudo_date, false))
                @if ($collaudoDaysDiff < 0)
                    {{ __('collaudo scaduto da :n gg', ['n' => abs($collaudoDaysDiff)]) }}
                @else
                    {{ __('collaudo in scadenza tra :n gg', ['n' => $collaudoDaysDiff]) }}
                @endif
            </div>
        @endif
    </td>
    <td class="code">{{ $equipment->vehicle?->internal_code ?? 'N/A' }}</td>
    <td class="code">{{ $equipment->vehicle?->license_plate ?? 'N/A' }}</td>
    <td><span class="badge {{ $statusBadgeClass }}">{{ $equipment->overall_status_label }}</span></td>
    <td>
        <div class="row-actions">
            <a href="{{ route('admin.equipments.show', $equipment->id) }}" class="mini-btn"
                title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
            <a href="{{ route('admin.equipments.edit', $equipment->id) }}" class="mini-btn"
                title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
            <button type="button" class="mini-btn" title="{{ __('Elimina') }}"
                data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
    </td>
</tr>
<x-admin.delete-modal type="equipment" :object="$equipment" />
