@props(['vehicle'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Pneumatici') }}</h3>
        <div style="display:flex; gap:6px;">
            <a href="{{ route('admin.maintenance-records.create', ['vehicle_id' => $vehicle->id, 'activity_type' => 'Cambio Gomme', 'back' => url()->full()]) }}"
                class="veh-btn-add" title="{{ __('Registra cambio gomme') }}"><i
                    class="fa-solid fa-arrows-rotate"></i></a>
            <a href="{{ route('admin.tires.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
                class="veh-btn-add" title="{{ __('Nuovo set di gomme') }}"><i class="fa-solid fa-plus"></i></a>
        </div>
    </div>
    <div class="body">
        @forelse ($vehicle->tires->sortByDesc('status') as $tire)
            <div class="veh-eq-item">
                <span class="ic"><i class="fa-solid fa-circle-dot"></i></span>
                <div style="min-width:0;">
                    <div class="name">{{ $tire->season_label }}
                        ({{ $tire->position_label }})
                        ·
                        {{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: __('N/A') }}</div>
                    <div class="meta">{{ $tire->size ?? 'N/A' }} ·
                        {{ __('cambio') }} {{ $tire->next_change_date_formatted ?? 'N/A' }}</div>
                </div>
                <span
                    class="exp c-{{ match ($tire->status) {
                        'mounted' => 'green',
                        'stored' => 'gray',
                        'retired' => 'red',
                        default => 'gray',
                    } }}">{{ $tire->status_label }}</span>
                <div class="row-actions">
                    <a href="{{ route('admin.tires.show', ['tire' => $tire->id, 'back' => url()->full()]) }}"
                        class="mini-btn" title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    <a href="{{ route('admin.tires.edit', ['tire' => $tire->id, 'back' => url()->full()]) }}"
                        class="mini-btn" title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal-{{ $tire->id }}"><i
                            class="fa-solid fa-trash"></i></button>
                </div>
                <x-admin.delete-modal type="tire" :object="$tire" />
            </div>
        @empty
            <div class="veh-eq-item"><div><div class="name">{{ __('Nessun set di gomme registrato') }}</div>
                </div></div>
        @endforelse
    </div>
</div>
