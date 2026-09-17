const CACHE = "khetha-path-v3";
const ASSETS = [
  "./", "./index.php", "./my-path.php", "./what-if.php", "./ask.html",
  "./assets/css/style.css", "./assets/js/app.js", "./manifest.json", "./assets/images/khetha-logo.png"
];
self.addEventListener("install", e => e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS))));
self.addEventListener("fetch", e => {
  if (e.request.method !== "GET") return;
  e.respondWith(caches.match(e.request).then(cached => cached || fetch(e.request).then(r => {
    const copy = r.clone();
    caches.open(CACHE).then(c => c.put(e.request, copy));
    return r;
  }).catch(() => caches.match("./index.php"))));
});