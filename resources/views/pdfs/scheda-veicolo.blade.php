@include('pdfs.partials.scheda-veicolo.styles')

@include('pdfs.partials.scheda-veicolo.header')

@include('pdfs.partials.scheda-veicolo.info-grid')

@include('pdfs.partials.scheda-veicolo.deadlines')

@include('pdfs.partials.scheda-veicolo.open-issues')

@include('pdfs.partials.scheda-veicolo.recent-maintenance')

@include('pdfs.partials.scheda-veicolo.issues-history')

@include('pdfs.partials.scheda-veicolo.equipment')

@include('pdfs.partials.scheda-veicolo.tires')

<!-- FOOTER -->
<div class="footer">
    <p>Documento generato automaticamente da CV Backoffice — Dati aggiornati al {{ now()->format('d/m/Y H:i') }}</p>
</div>
