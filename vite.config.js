import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

const viteDevHost = process.env.VITE_DEV_HOST ?? 'localhost';
const viteDevPort = Number(process.env.VITE_DEV_PORT ?? 5174);
const viteCacheDir = process.env.VITE_CACHE_DIR ?? 'node_modules/.vite';

export default defineConfig(({ command }) => ({
    cacheDir: viteCacheDir,
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.tsx'],
            refresh: command === 'serve',
        }),
        react(),
        tailwindcss(),
    ],
    build: {
        rolldownOptions: {
            checks: {
                pluginTimings: false,
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        hmr: {
            host: viteDevHost,
            clientPort: viteDevPort,
        },
    },
}));
