import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = 'XFlickr';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
        ) as any,
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
    },
});
