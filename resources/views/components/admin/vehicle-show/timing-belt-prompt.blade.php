@props(['vehicle'])

@if (session('timingBeltPrompt'))
    @php($timingBeltPrompt = session('timingBeltPrompt'))
    <div class="modal fade" id="timingBeltPromptModal" tabindex="-1"
        aria-labelledby="timingBeltPromptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="timingBeltPromptModalLabel">
                        {{ __('Cinghia di distribuzione') }}</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                </div>
                <div class="modal-body">
                    @if ($timingBeltPrompt['action'] === 'create')
                        {{ __('Il veicolo è ora segnato come dotato di cinghia di distribuzione, ma non ha ancora una scadenza cinghia collegata. Vuoi crearla ora, calcolata dalla data di immatricolazione?') }}
                    @elseif ($timingBeltPrompt['action'] === 'delete')
                        {{ __('Il veicolo non è più segnato come dotato di cinghia di distribuzione, ma esiste ancora una scadenza cinghia attiva (scad. :date). Vuoi eliminarla?', ['date' => $timingBeltPrompt['due_date']]) }}
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Ignora') }}</button>
                    @if ($timingBeltPrompt['action'] === 'create')
                        <form method="POST" action="{{ route('admin.vehicles.timing-belt-deadline.create', $vehicle) }}"
                            class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ __('Crea scadenza cinghia') }}</button>
                        </form>
                    @elseif ($timingBeltPrompt['action'] === 'delete')
                        <form method="POST" action="{{ route('admin.deadlines.destroy', $timingBeltPrompt['deadline_id']) }}"
                            class="m-0">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="back" value="{{ route('admin.vehicles.show', $vehicle->id) }}">
                            <button type="submit" class="btn btn-danger">{{ __('Elimina scadenza cinghia') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('timingBeltPromptModal');
            if (modalEl) {
                new bootstrap.Modal(modalEl).show();
            }
        });
    </script>
@endif
