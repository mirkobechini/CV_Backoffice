{{-- Dialog completamento: appare quando la data di rientro è compilata --}}
<div class="modal fade" id="completionModal" tabindex="-1" aria-labelledby="completionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completionModalLabel">{{ __('Completamento appuntamento') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
            </div>
            <div class="modal-body">
                <p class="hint" style="margin-bottom:12px;">
                    {{ __('Segna quali guasti e scadenze sono stati completati in questo appuntamento.') }}</p>
                <div id="completion-issues" style="margin-bottom:12px;">
                    <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Guasti') }}</h6>
                    <div id="completion-issues-list"></div>
                </div>
                <div id="completion-deadlines" style="margin-bottom:12px;">
                    <h6 style="font-size:12.5px; font-weight:700; margin-bottom:8px;">{{ __('Scadenze') }}</h6>
                    <div id="completion-deadlines-list"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                <button type="button" class="btn btn-success" id="completion-confirm-btn">{{ __('Conferma') }}</button>
            </div>
        </div>
    </div>
</div>
