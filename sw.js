/* Aqarat service worker — offline support + web push.
 * Lives in the install folder (e.g. /aqarat/sw.js) so its scope is that folder only. */
'use strict';

const VERSION = 'v7';
const SCOPE = new URL(self.registration.scope);           // e.g. https://example.com/aqarat/
const BASE = SCOPE.pathname.replace(/\/$/, '');            // e.g. /aqarat
const STATIC = `static-${VERSION}`;
const PAGES = `pages-${VERSION}`;
const IMAGES = `images-${VERSION}`;
const OFFLINE_URL = `${BASE}/offline`;
const PRECACHE = [OFFLINE_URL, `${BASE}/assets/img/icon-192.png`, `${BASE}/assets/img/favicon.svg`];
const PRIVATE = /\/(dashboard|admin|login|register|logout|push|api|lang|install|og)(\/|$)/;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [STATIC, PAGES, IMAGES];
        for (const key of await caches.keys()) if (!keep.includes(key)) await caches.delete(key);
        if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
        await self.clients.claim();
    })());
});

async function trim(cacheName, max) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin || !url.pathname.startsWith(BASE + '/')) return;
    const rel = url.pathname.slice(BASE.length);

    // Versioned static assets: cache-first
    if (rel.startsWith('/assets/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) { const copy = res.clone(); caches.open(STATIC).then((c) => c.put(req, copy)); }
            return res;
        })));
        return;
    }

    // Videos stream with HTTP range requests — never intercept them
    if (rel.startsWith('/uploads/') && !/\.webp$/.test(rel)) return;

    // Listing photos: cache-first, bounded
    if (rel.startsWith('/uploads/')) {
        event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
            if (res.ok) {
                const copy = res.clone();
                caches.open(IMAGES).then((c) => c.put(req, copy)).then(() => trim(IMAGES, 300));
            }
            return res;
        })));
        return;
    }

    // Public pages: network-first, fall back to the last copy, then the offline page
    if (req.mode === 'navigate' && !PRIVATE.test(rel)) {
        event.respondWith((async () => {
            try {
                const res = (await event.preloadResponse) || (await fetch(req));
                if (res.ok && res.type === 'basic') {
                    const copy = res.clone();
                    caches.open(PAGES).then((c) => c.put(req, copy)).then(() => trim(PAGES, 60));
                }
                return res;
            } catch {
                return (await caches.match(req)) || (await caches.match(OFFLINE_URL));
            }
        })());
        return;
    }

    // Private pages: never cached. Use the navigation-preload response when there is one — fetching again would
    // load the page twice, and the first copy (with its one-time flash message / form errors) would be lost.
    if (req.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                return (await event.preloadResponse) || (await fetch(req));
            } catch {
                return caches.match(OFFLINE_URL);
            }
        })());
    }
});

/* ---------- Push notifications ---------- */
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data && event.data.text() }; }
    const title = data.title || 'Aqarat';
    const options = {
        body: data.body || '',
        icon: data.icon || `${BASE}/assets/img/icon-192.png`,
        badge: data.badge || `${BASE}/assets/img/badge-72.png`,
        image: data.image || undefined,
        tag: data.tag || undefined,
        dir: data.dir || 'auto',
        lang: data.lang || undefined,
        data: { url: data.url || `${BASE}/` },
        renotify: Boolean(data.tag),
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    let target = event.notification.data && event.notification.data.url;
    try {
        target = new URL(target, SCOPE);
        if (target.origin !== location.origin) target = new URL(SCOPE); // only open our own pages
    } catch { target = new URL(SCOPE); }
    event.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of wins) {
            if (new URL(w.url).pathname.startsWith(BASE + '/') && 'navigate' in w) {
                await w.focus();
                return w.navigate(target.href);
            }
        }
        return self.clients.openWindow(target.href);
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'clear-pages') caches.delete(PAGES);
});
