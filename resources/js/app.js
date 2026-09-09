import './bootstrap';
import './vehicle-type-equipment';
import '@fortawesome/fontawesome-free/css/all.min.css';

import * as bootstrap from 'bootstrap';
// Espone bootstrap globalmente per gli script inline nelle viste
window.bootstrap = bootstrap;
import.meta.glob([
    '../img/**'
])

const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
    const icon = document.getElementById('theme-toggle-icon');

    if (icon) {
        icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('theme') || 'auto';

    applyTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'auto';
            const order = ['auto', 'light', 'dark'];
            const nextTheme = order[(order.indexOf(currentTheme) + 1) % order.length];

            localStorage.setItem('theme', nextTheme);
            applyTheme(nextTheme);
        });
    }

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
