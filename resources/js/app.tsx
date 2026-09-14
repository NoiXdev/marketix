import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName =
    document.querySelector('meta[name="app-name"]')?.getAttribute('content') ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob<{ default: ComponentType }>('./Pages/**/*.tsx'),
        ).then((module) => module.default),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
