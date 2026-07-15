import '../css/app.css';
import '@jooservices/react-layout/styles.css';
import '@jooservices/react-content/styles.css';
import '@jooservices/react-action-buttons/styles.css';
import '@jooservices/react-card/styles.css';
import '@jooservices/react-modal/styles.css';
import '@jooservices/react-toast/styles.css';
import '@jooservices/react-table/styles.css';
import '@jooservices/react-config/styles.css';
import '@jooservices/react-ui/styles.css';

import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@jooservices/react-toast';
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
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster position="top-right" />
            </>,
        );
    },
    progress: {
        color: '#2563eb',
    },
});
