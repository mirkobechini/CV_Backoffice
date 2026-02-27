@props(['type', 'object'])

@php
    $entityLabel = match ($type) {
        'vehicle' => 'veicolo',
        'provider' => 'officina',
        'issue' => 'guasto',
        'maintenanceRecord' => 'manutenzione',
        default => $type,
    };

    $destroyRouteName = match ($type) {
        'vehicle' => 'admin.vehicles.destroy',
        'provider' => 'admin.providers.destroy',
        'issue' => 'admin.issues.destroy',
        'maintenanceRecord' => 'admin.maintenancerecords.destroy',
        default => 'admin.' . strtolower($type) . 's.destroy',
    };

    $routeParameterName = match ($type) {
        'maintenanceRecord' => 'maintenanceRecord',
        default => $type,
    };
    $routeParameterValue = $object?->getRouteKey();
    $modalIdSuffix = $routeParameterValue ?? 'missing-' . $type;

    $displayValue = match ($type) {
        'vehicle' => $object->internal_code ?? ($object->license_plate ?? (string) $object->id),
        'provider' => $object->name ?? (string) $object->id,
        'issue' => $object->description ?? (string) $object->id,
        'maintenanceRecord' => $object->activity_type ?? (string) $object->id,
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
                @if ($type === 'vehicle')
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
