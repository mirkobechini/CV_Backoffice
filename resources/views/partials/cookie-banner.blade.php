<div id="cookie-banner" class="cookie-banner is-hidden" role="dialog" aria-label="Cookie">
    <div class="cookie-banner-content">
        <p class="cookie-banner-title">🍪 {{ __('Utilizziamo i cookie') }}</p>
        <p class="cookie-banner-text">
            Questo sito utilizza solo cookie tecnici necessari al funzionamento (sessione, CSRF) e cookie di
            preferenza (tema chiaro/scuro). Non utilizziamo cookie di profilazione. Consulta la
            <a href="{{ route('privacy') }}">informativa privacy</a> per maggiori informazioni.
        </p>
        <div class="cookie-banner-actions">
            <button type="button" class="btn primary" id="cookie-accept">{{ __('Accetta') }}</button>
            <button type="button" class="btn" id="cookie-decline">{{ __('Rifiuta') }}</button>
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
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: 0 4px 20px rgba(0, 0, 0, .15);
        padding: 16px;
        z-index: 1080;
        color: var(--text);
    }

    .cookie-banner.is-hidden {
        display: none;
    }

    .cookie-banner-title {
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .cookie-banner-text {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 12px;
        line-height: 1.5;
    }

    .cookie-banner-text a {
        color: var(--primary);
    }

    .cookie-banner-actions {
        display: flex;
        gap: 8px;
    }
</style>

<script>
    (function() {
        const banner = document.getElementById('cookie-banner');
        const choice = localStorage.getItem('cookie_consent');

        if (!choice) {
            banner.classList.remove('is-hidden');
        }

        document.getElementById('cookie-accept').addEventListener('click', function() {
            localStorage.setItem('cookie_consent', 'accepted');
            banner.classList.add('is-hidden');
        });

        document.getElementById('cookie-decline').addEventListener('click', function() {
            localStorage.setItem('cookie_consent', 'declined');
            banner.classList.add('is-hidden');
        });
    })();
</script>
