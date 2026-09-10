/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/**/*.php',
    ],
    darkMode: ['selector', '[data-bs-theme="dark"]'],
    theme: {
        extend: {
            colors: {
                // Tema Dashboard Pro — variabili CSS (vedi resources/css/app.css)
                surface: {
                    DEFAULT: 'var(--surface)',
                    '2': 'var(--surface-2)',
                    '3': 'var(--surface-3)',
                },
                ink: {
                    DEFAULT: 'var(--text)',
                    muted: 'var(--text-muted)',
                },
                line: 'var(--border)',
                brand: {
                    DEFAULT: 'var(--primary)',
                    soft: 'var(--primary-soft)',
                },
                ok: {
                    DEFAULT: 'var(--green)',
                    soft: 'var(--green-soft)',
                },
                warn: {
                    DEFAULT: 'var(--amber)',
                    soft: 'var(--amber-soft)',
                },
                danger: {
                    DEFAULT: 'var(--red)',
                    soft: 'var(--red-soft)',
                },
                info: {
                    DEFAULT: 'var(--blue)',
                    soft: 'var(--blue-soft)',
                },
                accent: {
                    DEFAULT: 'var(--purple)',
                    soft: 'var(--purple-soft)',
                },
            },
        },
    },
    plugins: [],
};