const CACHE = "khetha-path-v7";

// Static shell assets only — safe to cache-first, since a stale copy for
// a few seconds after a deploy is harmless and they rarely change.
// Dynamic, session-dependent .php pages are NOT precached here: every
// page in this app renders per-session state (login, dashboard progress,
// assessment results), so caching one at install time and serving it
// forever — which is what this file did before — is exactly what made a
// design or logic change on a .php page invisible until the cache was
// cleared by hand. They're handled by the network-first branch below
// instead, which still falls back to a cached copy offline.
const STATIC_ASSETS = [
    "./assets/css/style.css", "./assets/js/app.js",
    "./assets/images/khetha-logo.png", "./manifest.json",
];
const STATIC_ASSET_RE = /\.(css|js|png|jpe?g|svg|webp|ico|gif|woff2?|json)$/;

self.addEventListener("install", e => {
    e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener("activate", e => {
    e.waitUntil(
        caches.keys()
            .then(names => Promise.all(names.filter(name => name !== CACHE).map(name => caches.delete(name))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", e => {
    if (e.request.method !== "GET") return;
    const url = new URL(e.request.url);

    if (STATIC_ASSET_RE.test(url.pathname)) {
        // Cache-first: instant load, refreshed from network in the background.
        e.respondWith(
            caches.match(e.request).then(cached => {
                const network = fetch(e.request)
                    .then(r => { caches.open(CACHE).then(c => c.put(e.request, r.clone())); return r; })
                    .catch(() => cached);
                return cached || network;
            })
        );
        return;
    }

    // Network-first for every .php page and everything else: always try
    // to get the current version, only falling back to a cached copy —
    // or the offline shell — once the network genuinely isn't available.
    e.respondWith(
        fetch(e.request).then(r => {
            const copy = r.clone();
            caches.open(CACHE).then(c => c.put(e.request, copy));
            return r;
        }).catch(() =>
            caches.match(e.request).then(cached => cached || caches.match("./index.php"))
        )
    );
});
