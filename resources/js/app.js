import './bootstrap';
import './vehicle-type-equipment';
import './date-pickers';
import './tire-size-input';
import './heic-convert';
import './fuel-card-select';
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

    // Sidebar off-canvas su tablet/mobile (<992px): hamburger nella topbar
    // (768-991px) o voce "Menu" della bottom nav (<768px, restyle "Saru").
    const sidebar = document.querySelector('.sidebar-dp');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const bottomNavMenuToggle = document.getElementById('bottom-nav-menu-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const bottomNav = document.querySelector('.bottom-nav');

    const openSidebar = () => {
        sidebar.classList.add('is-open');
        sidebarBackdrop.classList.add('is-open');
        // Nasconde l'hamburger mentre il menu è aperto: la sidebar
        // stessa resta l'unico modo per navigare o chiuderla.
        if (sidebarToggle) sidebarToggle.hidden = true;
        bottomNav?.classList.add('is-hidden');
    };

    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        sidebarBackdrop?.classList.remove('is-open');
        if (sidebarToggle) sidebarToggle.hidden = false;
        bottomNav?.classList.remove('is-hidden');
    };

    if (sidebar && sidebarBackdrop) {
        sidebarToggle?.addEventListener('click', openSidebar);
        bottomNavMenuToggle?.addEventListener('click', openSidebar);

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

    // Bottom nav: menu azione rapida ("+")
    const addToggle = document.getElementById('bottom-nav-add-toggle');
    const addMenu = document.getElementById('bottom-nav-add-menu');

    if (addToggle && addMenu) {
        const closeAddMenu = () => {
            addMenu.classList.remove('is-open');
            addToggle.setAttribute('aria-expanded', 'false');
        };

        addToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = addMenu.classList.toggle('is-open');
            addToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', (event) => {
            if (!addMenu.contains(event.target) && event.target !== addToggle) {
                closeAddMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeAddMenu();
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

    // Righe di gruppo pieghevoli nelle tabelle raggruppate (guasti,
    // scadenze, appuntamenti): un click sull'intestazione nasconde tutte
    // le righe che le appartengono, incluse quelle di eventuali sotto-gruppi
    // annidati (che portano l'id del gruppo tra i propri data-groups).
    const collapsedGroups = new Set();

    const applyGroupCollapse = () => {
        document.querySelectorAll('[data-groups]').forEach((row) => {
            const groups = row.dataset.groups.split(' ').filter(Boolean);
            row.style.display = groups.some((g) => collapsedGroups.has(g)) ? 'none' : '';
        });
        document.querySelectorAll('.group-row[data-group-row]').forEach((header) => {
            const isCollapsed = collapsedGroups.has(header.dataset.groupRow);
            header.classList.toggle('is-collapsed', isCollapsed);
            header.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
        });
    };

    const toggleGroupRow = (header) => {
        const id = header.dataset.groupRow;
        if (collapsedGroups.has(id)) {
            collapsedGroups.delete(id);
        } else {
            collapsedGroups.add(id);
        }
        applyGroupCollapse();
    };

    document.addEventListener('click', (event) => {
        const header = event.target.closest('.group-row[data-group-row]');
        if (!header) return;

        toggleGroupRow(header);
    });

    // Tastiera: le righe di gruppo sono <tr role="button" tabindex="0">,
    // non elementi nativamente interattivi — serve il binding esplicito
    // di Invio/Spazio per essere operabili senza mouse (audit 2026-10-19).
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;

        const header = event.target.closest('.group-row[data-group-row]');
        if (!header) return;

        event.preventDefault();
        toggleGroupRow(header);
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
