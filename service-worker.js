const VERSION = "v10";
const STATIC_CACHE = "khetha-static-" + VERSION;
const PAGE_CACHE = "khetha-pages-" + VERSION;

// App shell, precached at install so the site opens with no connection.
const SHELL = [
    "offline.html",
    "assets/css/style.css", "assets/js/app.js", "assets/js/pwa.js",
    "assets/images/khetha-logo.png", "assets/images/icon-192.png", "assets/images/icon-512.png",
    "manifest.json",
];

// Third-party styling every Bootstrap page needs. Precached separately so
// one CDN hiccup can't fail the whole install.
const CDN = [
    "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css",
    "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js",
    "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css",
];

// Signed-in pages saved for offline use once the learner reaches their
// dashboard (see assets/js/pwa.js).
const WARM_PAGES = [
    "dashboard.php", "subject.php", "career-quiz.php", "occupation.php", "my-path.php",
    "directories.php", "favourites.php", "advice.php", "what-if.php", "contact-advisor.php", "ask.php", "settings.php",
];

const STATIC_RE = /\.(css|js|png|jpe?g|svg|webp|ico|gif|woff2?|json)$/;

self.addEventListener("install", e => {
    e.waitUntil(
        caches.open(STATIC_CACHE).then(async c => {
            await c.addAll(SHELL);
            await Promise.all(CDN.map(u => c.add(new Request(u, { mode: "cors" })).catch(() => {})));
        })
    );
    self.skipWaiting();
});

self.addEventListener("activate", e => {
    e.waitUntil(
        caches.keys()
            .then(names => Promise.all(names.filter(n => n !== STATIC_CACHE && n !== PAGE_CACHE).map(n => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener("message", e => {
    if (!e.data || e.data.type !== "warm") return;
    e.waitUntil(
        caches.open(PAGE_CACHE).then(c => Promise.all(WARM_PAGES.map(p =>
            fetch(p, { credentials: "same-origin" })
                .then(r => { if (r.ok && !r.redirected) return c.put(p, r); })
                .catch(() => {})
        ))).then(() => { if (e.source) e.source.postMessage({ type: "warmed" }); })
    );
});

self.addEventListener("fetch", e => {
    const req = e.request;
    if (req.method !== "GET") return;
    const url = new URL(req.url);
    const sameOrigin = url.origin === self.location.origin;

    // Signing out wipes the saved pages so the next person on a shared phone
    // never sees the previous learner's data.
    if (sameOrigin && /\/logout\.php$/.test(url.pathname)) {
        e.respondWith(fetch(req).finally(() => caches.delete(PAGE_CACHE)));
        return;
    }

    // Live endpoints are never cached. account.php is in this list because it
    // carries a CSRF token and the learner's email: a stale copy would show an
    // expired token, and a saved copy would outlive signing out.
    if (sameOrigin && /\/(ask-api\.php|account\.php|api\/)/.test(url.pathname)) return;

    // Static files and CDN assets: cache-first, refreshed in the background.
    if (!sameOrigin || STATIC_RE.test(url.pathname)) {
        e.respondWith(
            caches.match(req).then(cached => {
                const network = fetch(req)
                    .then(r => {
                        if (r.ok || r.type === "opaque") {
                            const copy = r.clone();
                            caches.open(STATIC_CACHE).then(c => c.put(req, copy));
                        }
                        return r;
                    })
                    .catch(() => cached);
                return cached || network;
            })
        );
        return;
    }

    // Pages: network-first so changes show straight away, falling back to the
    // last saved copy, then the offline screen. Redirects (e.g. an expired
    // session bouncing to login) are never saved.
    e.respondWith(
        fetch(req)
            .then(r => {
                if (r.ok && !r.redirected) {
                    const copy = r.clone();
                    caches.open(PAGE_CACHE).then(c => c.put(req, copy));
                }
                return r;
            })
            .catch(async () => {
                const cached = await caches.match(req);
                if (cached) return cached;
                if (req.mode !== "navigate") return Response.error();
                // The home screen icon opens index.php, which redirects a
                // signed-in learner; offline, send them to their dashboard.
                if (/(^|\/)(index\.php)?$/.test(url.pathname)) {
                    const dash = await caches.match("dashboard.php");
                    if (dash) return dash;
                }
                return caches.match("offline.html");
            })
    );
});
