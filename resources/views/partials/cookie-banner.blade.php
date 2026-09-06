<div id="cookie-banner" class="cookie-banner d-none" role="dialog" aria-label="Cookie">
    <div class="cookie-banner-content">
        <p class="mb-2">
            <strong>🍪 Utilizziamo i cookie</strong>
        </p>
        <p class="mb-3 small">
            Questo sito utilizza solo cookie tecnici necessari al funzionamento (sessione, CSRF) e cookie di
            preferenza (tema chiaro/scuro). Non utilizziamo cookie di profilazione. Consulta la
            <a href="{{ route('privacy') }}">informativa privacy</a> per maggiori informazioni.
        </p>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="cookie-accept">Accetta</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="cookie-decline">Rifiuta</button>
        </div>
    </div>
</div>

<style>
    .cookie-banner {
        position: fixed;
        bottom: 20px;
        left: 20px;
        right: 20px;
        max-width: 420px;
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        padding: 16px;
        z-index: 1080;
    }

    .cookie-banner-content {
        color: var(--bs-body-color);
    }
</style>

<script>
    (function() {
        const banner = document.getElementById('cookie-banner');
        const choice = localStorage.getItem('cookie_consent');

        if (!choice) {
            banner.classList.remove('d-none');
        }

        document.getElementById('cookie-accept').addEventListener('click', function() {
            localStorage.setItem('cookie_consent', 'accepted');
            banner.classList.add('d-none');
        });

        document.getElementById('cookie-decline').addEventListener('click', function() {
            localStorage.setItem('cookie_consent', 'declined');
            banner.classList.add('d-none');
        });
    })();
</script>
