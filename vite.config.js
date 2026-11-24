import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path'; // <-- require path from node

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/equipment-qr-scan.js', 'resources/js/maintenance-calendar.js'],
            refresh: true,
        }),
    ],
    // Add resolve object and aliases
    resolve: {
        alias: {
            '~resources': '/resources/'
        }
    },
    build: {
        // heic2any (decoder WASM libheif, ~1.3MB) è già in dynamic import,
        // caricato solo da chi carica davvero un file HEIC/HEIF — non c'è
        // altro da frazionare, il warning di default (500kB) è falso allarme.
        chunkSizeWarningLimit: 1500,
    },
});
