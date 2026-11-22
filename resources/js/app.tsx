import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
const pages = {
    Home: () => import('./pages/Home').then(page => page.default),
    Project: () => import('./pages/Project').then(page => page.default),
    Login: () => import('./pages/Admin').then(page => page.Login),
    Dashboard: () => import('./pages/Admin').then(page => page.Dashboard),
    Audit: () => import('./pages/Admin').then(page => page.Audit),
    Records: () => import('./pages/Records').then(page => page.Records),
    Settings: () => import('./pages/Records').then(page => page.Settings),
    Inbox: () => import('./pages/Inbox').then(page => page.Inbox),
    Thread: () => import('./pages/Inbox').then(page => page.Thread),
};

void createInertiaApp({
    resolve: (name) => pages[name as keyof typeof pages](),
    setup: ({ el, App, props }) => createRoot(el).render(<App {...props} />),
    progress: { color: '#ff875c' },
});

if (import.meta.env.PROD && 'serviceWorker' in navigator && document.querySelector('link[rel="manifest"]')) {
    window.addEventListener('load', () => {
        const needsWarmup = !navigator.serviceWorker.controller;
        void navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).then(async () => {
            if (!needsWarmup) return;
            const registration = await navigator.serviceWorker.ready;
            const images = Array.from(document.images, image => new URL(image.src, location.origin).pathname)
                .filter(path => path.startsWith('/images/'));
            registration.active?.postMessage({ type: 'warm-public-page', images });
        }).catch(() => {});
    });
}
