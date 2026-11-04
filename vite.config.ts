import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // The dev server runs inside Docker, so listen on all interfaces but
        // advertise localhost to the browser (the host publishes 5173).
        origin: 'http://localhost:5173',
        // The page is served from http://localhost (port 80), while the dev
        // server lives on :5173. Without this, laravel-vite-plugin reuses
        // `origin` as the only allowed CORS origin and the browser blocks
        // every module script, leaving a blank page.
        cors: {
            origin: [
                'http://localhost',
                'http://localhost:5173',
                'http://127.0.0.1',
                'http://127.0.0.1:5173',
            ],
        },
        hmr: {
            host: 'localhost',
            clientPort: 5173,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
