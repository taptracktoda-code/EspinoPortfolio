/* The server prepends BUILD_VERSION and STATIC_ASSETS from the active Vite build. */
const PREFIX = 'portfolio-';
const STATIC = PREFIX + 'static-' + BUILD_VERSION;
const PAGES = PREFIX + 'pages-' + BUILD_VERSION;
const IMAGES = PREFIX + 'images-' + BUILD_VERSION;
const OFFLINE = '/offline.html';
const publicPath = path => path === '/' || /^\/projects\/[a-z0-9-]+$/.test(path);
const imagePath = path => /^\/images\/[a-f0-9-]+$/.test(path);
const shell = [OFFLINE, '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png',
    '/icons/maskable-512.png', '/icons/apple-touch-icon.png', ...STATIC_ASSETS];
const staticPaths = new Set(shell);

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC);
        await cache.addAll(shell.map(path => new Request(path, { cache: 'reload' })));
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter(key => key.startsWith(PREFIX) && ![STATIC, PAGES, IMAGES].includes(key)).map(key => caches.delete(key)));
        await self.clients.claim();
    })());
});

async function persist(cache, key, response, limit) {
    try {
        await cache.put(key, response.clone());
        const entries = await cache.keys();
        for (const entry of entries.slice(0, Math.max(entries.length - limit, 0))) {
            await cache.delete(entry);
        }
    } catch {
        // Caching is best effort when storage space is limited.
    }
}

async function fetchPage(url) {
    const cache = await caches.open(PAGES);
    const key = new URL(url.pathname, self.location.origin).href;
    try {
        const response = await fetch(key, { credentials: 'omit', cache: 'no-cache', headers: { Accept: 'text/html' } });
        if (response.ok && response.headers.get('X-Portfolio-Public') === 'page' &&
            response.headers.get('X-Portfolio-Build') === PAGE_VERSION &&
            response.headers.get('Content-Type')?.includes('text/html')) {
            await persist(cache, key, response, 30);
        } else if (response.status === 404 || response.status === 410) {
            await cache.delete(key);
        }
        return response;
    } catch {
        const snapshot = await cache.match(key);
        if (!snapshot) return (await caches.match(OFFLINE)) || Response.error();

        const html = (await snapshot.text()).replace('</head>', '<meta name="portfolio-offline" content="true"></head>');
        const headers = new Headers(snapshot.headers);
        headers.delete('Content-Encoding');
        headers.delete('Content-Length');
        return new Response(html, { headers });
    }
}

async function fetchImage(cache, key) {
    const response = await fetch(key, { credentials: 'omit', cache: 'no-cache' });
    if (response.ok && response.headers.get('X-Portfolio-Public') === 'image' &&
        response.headers.get('Content-Type')?.startsWith('image/')) {
        await persist(cache, key, response, 80);
    } else if (response.status === 404 || response.status === 410) {
        await cache.delete(key);
    }
    return response;
}

self.addEventListener('message', event => {
    if (event.data?.type !== 'warm-public-page' || !event.source?.url) return;
    const url = new URL(event.source.url);
    if (url.origin !== self.location.origin || !publicPath(url.pathname)) return;
    const images = Array.isArray(event.data.images) ? event.data.images.slice(0, 18) : [];
    event.waitUntil((async () => {
        const cache = await caches.open(IMAGES);
        await Promise.allSettled([
            fetchPage(url),
            ...images.filter(path => typeof path === 'string' && imagePath(path))
                .map(path => fetchImage(cache, new URL(path, self.location.origin).href)),
        ]);
    })());
});

self.addEventListener('fetch', event => {
    const { request } = event;
    if (request.method !== 'GET' || request.headers.has('X-Inertia') || request.headers.has('Range')) return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate' && publicPath(url.pathname) && !url.search) {
        event.respondWith(fetchPage(url));
    } else if (request.destination === 'image' && imagePath(url.pathname) && !url.search) {
        const cache = caches.open(IMAGES);
        const fresh = cache.then(store => fetchImage(store, url.href));
        event.waitUntil(fresh.catch(() => {}));
        event.respondWith(cache.then(async store => (await store.match(url.href)) || fresh));
    } else if (request.mode !== 'navigate' && staticPaths.has(url.pathname) && !url.search) {
        event.respondWith(caches.open(STATIC).then(async cache => (await cache.match(url.pathname)) || fetch(request)));
    }
});
