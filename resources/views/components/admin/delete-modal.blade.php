@props(['type', 'object'])

@php
    $normalizedType = strtolower($type);
    $resourceConfig = [
        'vehicle' => ['label' => 'veicolo', 'route' => 'admin.vehicles.destroy', 'parameter' => 'vehicle'],
        'provider' => ['label' => 'officina', 'route' => 'admin.providers.destroy', 'parameter' => 'provider'],
        'issue' => ['label' => 'guasto', 'route' => 'admin.issues.destroy', 'parameter' => 'issue'],
        'maintenancerecord' => ['label' => 'manutenzione', 'route' => 'admin.maintenance-records.destroy', 'parameter' => 'maintenanceRecord'],
        'vehicletype' => ['label' => 'tipo veicolo', 'route' => 'admin.vehicle-types.destroy', 'parameter' => 'vehicleType'],
        'equipmenttype' => ['label' => 'tipo di attrezzatura', 'route' => 'admin.equipment-types.destroy', 'parameter' => 'equipmentType'],
        'equipment' => ['label' => 'attrezzatura', 'route' => 'admin.equipments.destroy', 'parameter' => 'equipment'],
        'deadline' => ['label' => 'scadenza', 'route' => 'admin.deadlines.destroy', 'parameter' => 'deadline'],
        'mileagelog' => ['label' => 'registro chilometrico', 'route' => 'admin.mileage-logs.destroy', 'parameter' => 'mileageLog'],
    ];

    $config = $resourceConfig[$normalizedType] ?? [
        'label' => $type,
        'route' => 'admin.' . $normalizedType . 's.destroy',
        'parameter' => $type,
    ];

    $entityLabel = $config['label'];
    $destroyRouteName = $config['route'];
    $routeParameterName = $config['parameter'];
    $routeParameterValue = $object?->getRouteKey();
    $modalIdSuffix = $routeParameterValue ?? 'missing-' . $normalizedType;

    $displayValue = match ($normalizedType) {
        'vehicle' => $object->internal_code ?? ($object->license_plate ?? (string) $object->id),
        'issue' => $object->description ?? (string) $object->id,
        'maintenancerecord' => $object->activity_type ?? (string) $object->id,
        default => $object->name ?? ($object->title ?? (string) $object->id),
    };
@endphp

<div class="modal fade" id="confirmDeleteModal-{{ $modalIdSuffix }}" tabindex="-1"
    aria-labelledby="confirmDeleteModalLabel-{{ $modalIdSuffix }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="confirmDeleteModalLabel-{{ $modalIdSuffix }}">Confermare eliminazione
                </h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare "{{ $displayValue }}"
                @if ($normalizedType === 'vehicle')
                    , tutti i guasti e le manutenzioni associate
                @endif
                ?
                <br>
                Questa azione non può essere annullata.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                    aria-label="Annulla eliminazione {{ $entityLabel }} {{ $displayValue }}">Annulla</button>
                @if ($routeParameterValue)
                    <form action="{{ route($destroyRouteName, [$routeParameterName => $routeParameterValue]) }}"
                        method="POST" data-single-submit="true">
                        @csrf
                        @method('DELETE')
                        <input type="submit" class="btn btn-danger" value="Elimina definitivamente"
                            data-loading-text="Eliminazione..."
                            aria-label="Conferma eliminazione {{ $entityLabel }} {{ $displayValue }}">
                    </form>
                @else
                    <button type="button" class="btn btn-danger" disabled>Elimina non disponibile</button>
                @endif
            </div>
        </div>
    </div>
</div>
