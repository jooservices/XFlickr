import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

const viteDevHost = process.env.VITE_DEV_HOST ?? 'localhost';
const viteDevPort = Number(process.env.VITE_DEV_PORT ?? 5174);

export default defineConfig(({ command }) => ({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: command === 'serve',
        }),
        react(),
        tailwindcss(),
    ],
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
