@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="card p-4 shadow rounded-lg">
            <h1 class="mb-4">Informativa Privacy</h1>

            <p class="text-muted">Ultimo aggiornamento: {{ now()->format('d/m/Y') }}</p>

            <h2 class="mt-4 fs-5">1. Titolare del trattamento</h2>
            <p>
                Il Titolare del trattamento dei dati è {{ config('app.name', 'CV Backoffice') }}, gestito da
                {{ config('app.name', 'CV Backoffice') }}. Per qualsiasi richiesta relativa ai tuoi dati personali,
                contattaci tramite i canali indicati nell'applicazione.
            </p>

            <h2 class="mt-4 fs-5">2. Dati personali trattati</h2>
            <p>L'applicazione tratta i seguenti dati personali:</p>
            <ul>
                <li><strong>Dati di registrazione</strong>: nome, email, password (crittografata)</li>
                <li><strong>Dati di gestione flotta</strong>: targhe, codici interni, chilometraggi, scadenze, guasti,
                    manutenzioni</li>
                <li><strong>Immagini</strong>: foto di guasti e documenti caricati</li>
                <li><strong>Dati di utilizzo</strong>: log di accesso, token API</li>
            </ul>

            <h2 class="mt-4 fs-5">3. Finalità del trattamento</h2>
            <p>I dati sono trattati per le seguenti finalità:</p>
            <ul>
                <li>Gestione e manutenzione della flotta di mezzi</li>
                <li>Notifiche relative a scadenze, guasti e manutenzioni</li>
                <li>Invio di report periodici (se configurato)</li>
                <li>Adempimento di obblighi di legge</li>
            </ul>

            <h2 class="mt-4 fs-5">4. Base giuridica</h2>
            <p>
                Il trattamento si basa sull'esecuzione di un contratto di cui l'interessato è parte (art. 6, par. 1,
                lett. b, GDPR) e sul legittimo interesse del titolare (art. 6, par. 1, lett. f, GDPR).
            </p>

            <h2 class="mt-4 fs-5">5. Conservazione dei dati</h2>
            <p>
                I dati sono conservati per il tempo necessario alle finalità sopra indicate. I dati di gestione flotta
                sono conservati per l'intera durata del rapporto e successivamente per gli obblighi di legge.
            </p>

            <h2 class="mt-4 fs-5">6. Diritti dell'interessato</h2>
            <p>Ai sensi degli artt. 15-22 GDPR, hai il diritto di:</p>
            <ul>
                <li><strong>Accesso</strong> ai tuoi dati personali</li>
                <li><strong>Rettifica</strong> dei dati inesatti</li>
                <li><strong>Cancellazione</strong> dei tuoi dati (diritto all'oblio)</li>
                <li><strong>Limitazione</strong> del trattamento</li>
                <li><strong>Portabilità</strong> dei dati (puoi esportarli dalla sezione profilo)</li>
                <li><strong>Opposizione</strong> al trattamento</li>
            </ul>
            <p>
                Puoi esercitare questi diritti tramite la sezione profilo dell'applicazione (esportazione dati,
                eliminazione account) o contattando il titolare.
            </p>

            <h2 class="mt-4 fs-5">7. Cookie</h2>
            <p>
                L'applicazione utilizza cookie tecnici necessari al funzionamento (sessione, CSRF) e cookie di
                preferenza (tema chiaro/scuro). Non vengono utilizzati cookie di profilazione o di terze parti.
            </p>

            <h2 class="mt-4 fs-5">8. Sicurezza</h2>
            <p>
                I dati sono protetti con misure di sicurezza adeguate: password crittografate, autenticazione con
                token, rate limiting e controllo degli accessi basato sui ruoli.
            </p>
        </div>
    </div>
@endsection
