import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path'; // <-- require path from node

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Add resolve object and aliases
    resolve: {
        alias: {
            '~resources': '/resources/'
        }
    },
});
