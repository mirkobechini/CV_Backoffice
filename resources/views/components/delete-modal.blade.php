@props(['type', 'object'])

<div class="modal fade" id="confirmDeleteModal-{{ $object->id }}" tabindex="-1" aria-labelledby="confirmDeleteModalLabel-{{ $object->id }}"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="confirmDeleteModalLabel-{{ $object->id }}">Confermare eliminazione</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Sei sicuro di voler eliminare "{{ $object->name }}"
                @if($type === 'vehicle')
                e tutti i guasti, manutenzioni e log di chilometraggio associati?
                @endif
                ? Questa azione non può essere
                annullata.
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" aria-label="Annulla eliminazione del {{ $type }} {{ $object->name }}">Annulla</button>
                <form action="{{ route('admin.' . $type . 's.destroy', $object->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="submit" class="btn btn-danger" value="Elimina definitivamente" aria-label="Conferma eliminazione del {{ $type }} {{ $object->name }}">
                </form>
            </div>
        </div>
    </div>
</div>
