@props(['vehicle', 'missingEquipment'])

{{-- Vista compatta sola lettura per la tab Panoramica (restyle "Saru",
     ADR §19): la card interattiva completa resta nella tab Attrezzatura. --}}
<div class="veh-card">
    <div class="head">
        <h3>{{ __('Equipaggiamento') }}</h3>
    </div>
    <div class="body">
        @forelse ($missingEquipment as $missingType)
            <div class="veh-dl-item">
                <div><div class="name">{{ $missingType->name }}</div></div>
                <span class="count c-red">{{ __('Mancante') }}</span>
            </div>
        @empty
        @endforelse
        @forelse ($vehicle->equipment as $equipment)
            <div class="veh-dl-item">
                <div><div class="name">{{ $equipment->equipmentType->name ?? $equipment->name ?? 'N/A' }}</div></div>
                <span
                    class="count c-{{ match ($equipment->status_color) {
                        'red' => 'red',
                        'yellow' => 'amber',
                        'green' => 'green',
                        default => 'gray',
                    } }}">{{ $equipment->status_label }}</span>
            </div>
        @empty
            @if ($missingEquipment->isEmpty())
                <div class="veh-dl-item">
                    <div><div class="name">{{ __('Nessun equipaggiamento registrato') }}</div></div>
                </div>
            @endif
        @endforelse
    </div>
</div>
