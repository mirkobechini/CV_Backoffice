import './bootstrap';
import '~resources/scss/app.scss';
import '~icons/bootstrap-icons.scss';
import '@fortawesome/fontawesome-free/css/all.min.css';

import * as bootstrap from 'bootstrap';
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
    const savedTheme = localStorage.getItem('theme') || 'light';

    applyTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            localStorage.setItem('theme', nextTheme);
            applyTheme(nextTheme);
        });
    }
});
