import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

const inDocker = process.env.VITE_IN_DOCKER === '1';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        // Inside the docker-compose.dev.yml `vite` container: listen on all interfaces,
        // tell the browser to use localhost, and poll — Windows bind mounts do not pass
        // file-change events into the container.
        ...(inDocker && {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5173',
            hmr: { host: 'localhost' },
        }),
        watch: {
            usePolling: inDocker,
            interval: 300,
            // vendor/ and storage/ hold thousands of files; polling them over a bind
            // mount stalls the dev server.
            ignored: ['**/storage/framework/views/**', ...(inDocker ? ['**/vendor/**', '**/storage/**'] : [])],
        },
    },
});
