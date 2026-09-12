import './bootstrap';
import './vehicle-type-equipment';
import './date-pickers';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'flatpickr/dist/flatpickr.min.css';
import 'flatpickr/dist/plugins/monthSelect/style.css';

import * as bootstrap from 'bootstrap';
// Espone bootstrap globalmente per gli script inline nelle viste
window.bootstrap = bootstrap;
import.meta.glob([
    '../img/**'
])

const applyTheme = (theme) => {
    // 'auto' rispetta la preferenza di sistema
    const effective = theme === 'auto'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : theme;

    document.documentElement.setAttribute('data-bs-theme', effective);

    // Evidenzia il bottone attivo nel theme switch
    const switchEl = document.getElementById('theme-switch');
    if (switchEl) {
        switchEl.querySelectorAll('button').forEach((btn) => {
            btn.classList.toggle('on', btn.dataset.theme === theme);
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const themeSwitch = document.getElementById('theme-switch');
    const savedTheme = localStorage.getItem('theme') || 'auto';

    applyTheme(savedTheme);

    if (themeSwitch) {
        themeSwitch.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-theme]');
            if (!btn) return;

            const nextTheme = btn.dataset.theme;
            localStorage.setItem('theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

    // Sidebar off-canvas su mobile (<992px): hamburger nella topbar
    const sidebar = document.querySelector('.sidebar-dp');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        sidebarBackdrop?.classList.remove('is-open');
        sidebarToggle.hidden = false;
    };

    if (sidebar && sidebarToggle && sidebarBackdrop) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.add('is-open');
            sidebarBackdrop.classList.add('is-open');
            // Nasconde l'hamburger mentre il menu è aperto: la sidebar
            // stessa resta l'unico modo per navigare o chiuderla.
            sidebarToggle.hidden = true;
        });

        sidebarBackdrop.addEventListener('click', closeSidebar);

        // Chiude il menu quando si naviga verso un'altra pagina
        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeSidebar);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeSidebar();
            }
        });
    }

    // Occhiello mostra/nascondi password: delegato, funziona su qualunque
    // campo con markup .password-field > input + button.password-toggle
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('.password-toggle');
        if (!toggle) return;

        const input = toggle.closest('.password-field')?.querySelector('input');
        if (!input) return;

        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        toggle.querySelector('i')?.classList.replace(showing ? 'fa-eye-slash' : 'fa-eye', showing ? 'fa-eye' : 'fa-eye-slash');
        toggle.setAttribute('aria-label', showing ? 'Mostra password' : 'Nascondi password');
    });

    document.querySelectorAll('form[data-single-submit="true"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitting = 'true';

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.disabled = true;

                if (!control.dataset.loadingText) {
                    return;
                }

                if (control.tagName === 'INPUT') {
                    control.value = control.dataset.loadingText;
                    return;
                }

                control.textContent = control.dataset.loadingText;
            });
        });
    });
});
