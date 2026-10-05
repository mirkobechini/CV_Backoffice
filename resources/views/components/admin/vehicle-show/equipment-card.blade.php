@props(['vehicle', 'assignableEquipment', 'missingEquipment'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Equipaggiamento') }}</h3>
        <div class="dropdown">
            <button type="button" class="veh-btn-add" title="{{ __('Aggiungi attrezzatura') }}"
                data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                <i class="fa-solid fa-plus"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end eq-assign-menu">
                @forelse ($assignableEquipment as $candidate)
                    <li>
                        <form method="POST" action="{{ route('admin.vehicles.equipment.assign', $vehicle) }}"
                            class="eq-assign-form">
                            @csrf
                            <input type="hidden" name="equipment_id" value="{{ $candidate->id }}">
                            <button type="submit" class="dropdown-item"
                                data-assigned="{{ $candidate->vehicle_id ? '1' : '0' }}"
                                data-assigned-to="{{ $candidate->vehicle->internal_code ?? '' }}">
                                {{ $candidate->equipmentType->name ?? $candidate->name }} ·
                                {{ $candidate->serial_number ?? 'N/A' }}
                                @if ($candidate->vehicle)
                                    <span class="eq-assign-meta">— {{ __('su') }} {{ $candidate->vehicle->internal_code }}</span>
                                @else
                                    <span class="eq-assign-meta">— {{ __('non assegnata') }}</span>
                                @endif
                            </button>
                        </form>
                    </li>
                @empty
                    <li><span class="dropdown-item-text eq-assign-empty">{{ __('Nessuna attrezzatura esistente da assegnare') }}</span></li>
                @endforelse
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item"
                        href="{{ route('admin.equipments.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}">
                        <i class="fa-solid fa-plus"></i> {{ __('Nuova attrezzatura') }}
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <div class="body">
        @if ($missingEquipment->isNotEmpty())
            @foreach ($missingEquipment as $missingType)
                @php($availableQuantity = $vehicle->equipment->where('equipment_type_id', $missingType->id)->count())
                @php($requiredQuantity = (int) $missingType->pivot->required_quantity)
                @php($matchingEquipment = $assignableEquipment->where('equipment_type_id', $missingType->id))
                <div class="veh-eq-item">
                    <span class="ic" style="color:var(--red);"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <div style="min-width:0;">
                        <div class="name">{{ $missingType->name }}</div>
                        <div class="meta">{{ __('Presenti :available di :required richieste', ['available' => $availableQuantity, 'required' => $requiredQuantity]) }}</div>
                    </div>
                    <span class="exp c-red">{{ __('Mancante') }}</span>
                    <div class="row-actions">
                        <div class="dropdown">
                            <button type="button" class="mini-btn" title="{{ __('Aggiungi') }}"
                                data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end eq-assign-menu">
                                @forelse ($matchingEquipment as $candidate)
                                    <li>
                                        <form method="POST"
                                            action="{{ route('admin.vehicles.equipment.assign', $vehicle) }}"
                                            class="eq-assign-form">
                                            @csrf
                                            <input type="hidden" name="equipment_id" value="{{ $candidate->id }}">
                                            <button type="submit" class="dropdown-item"
                                                data-assigned="{{ $candidate->vehicle_id ? '1' : '0' }}"
                                                data-assigned-to="{{ $candidate->vehicle->internal_code ?? '' }}">
                                                {{ $candidate->serial_number ?? $candidate->name }}
                                                @if ($candidate->vehicle)
                                                    <span class="eq-assign-meta">— {{ __('su') }} {{ $candidate->vehicle->internal_code }}</span>
                                                @else
                                                    <span class="eq-assign-meta">— {{ __('non assegnata') }}</span>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @empty
                                    <li><span class="dropdown-item-text eq-assign-empty">{{ __('Nessuna attrezzatura esistente di questo tipo') }}</span></li>
                                @endforelse
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item"
                                        href="{{ route('admin.equipments.create', ['vehicle_id' => $vehicle->id, 'equipment_type_id' => $missingType->id, 'back' => url()->full()]) }}">
                                        <i class="fa-solid fa-plus"></i> {{ __('Nuova attrezzatura') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
        @forelse ($vehicle->equipment as $equipment)
            <div class="veh-eq-item">
                <span class="ic"><i class="fa-solid fa-toolbox"></i></span>
                <div style="min-width:0;">
                    <div class="name">{{ $equipment->equipmentType->name ?? $equipment->name ?? 'N/A' }}</div>
                    <div class="meta">{{ $equipment->serial_number ?? 'N/A' }} ·
                        {{ __('rev.') }} {{ $equipment->revision_date_formatted ?? 'N/A' }}</div>
                </div>
                <span
                    class="exp c-{{ match ($equipment->status_color) {
                        'red' => 'red',
                        'yellow' => 'amber',
                        'green' => 'green',
                        default => 'gray',
                    } }}">{{ $equipment->status_label }}</span>
                <div class="row-actions">
                    <a href="{{ route('admin.equipments.show', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                        class="mini-btn" title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                        class="mini-btn" title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                    <button type="button" class="mini-btn" title="{{ __('Elimina') }}" data-bs-toggle="modal"
                        data-bs-target="#confirmDeleteModal-{{ $equipment->id }}"><i
                            class="fa-solid fa-trash"></i></button>
                </div>
                <x-admin.delete-modal type="equipment" :object="$equipment" />
            </div>
        @empty
            <div class="veh-eq-item"><div><div class="name">{{ __('Nessun equipaggiamento registrato') }}</div>
                </div></div>
        @endforelse
    </div>
</div>
