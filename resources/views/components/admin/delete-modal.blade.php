@props(['type', 'object'])

@php
    $entityLabel = match ($type) {
        'vehicle' => 'veicolo',
        'provider' => 'officina',
        'issue' => 'guasto',
        'maintenancerecord' => 'manutenzione',
        default => $type,
    };

    $displayValue = match ($type) {
        'vehicle' => $object->internal_code ?? ($object->license_plate ?? (string) $object->id),
        'provider' => $object->name ?? (string) $object->id,
        'issue' => $object->description ?? (string) $object->id,
        'maintenancerecord' => $object->activity_type ?? (string) $object->id,
        default => $object->name ?? ($object->title ?? (string) $object->id),
    };
@endphp

<div class="modal fade" id="confirmDeleteModal-{{ $object->id }}" tabindex="-1"
    aria-labelledby="confirmDeleteModalLabel-{{ $object->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="confirmDeleteModalLabel-{{ $object->id }}">Confermare eliminazione</h1>
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
                <form action="{{ route('admin.' . $type . 's.destroy', $object->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="submit" class="btn btn-danger" value="Elimina definitivamente"
                        aria-label="Conferma eliminazione {{ $entityLabel }} {{ $displayValue }}">
                </form>
            </div>
        </div>
    </div>
</div>
