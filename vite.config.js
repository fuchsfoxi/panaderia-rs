import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/css/login.css',
                    'resources/js/login.js',
                    'resources/css/dashboard.css',
                    'resources/js/dashboard.js',
                    'resources/css/produccion.css',
                    'resources/js/produccion.js',
                    'resources/css/historial.css',
                    'resources/js/historial.js'
                ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
});