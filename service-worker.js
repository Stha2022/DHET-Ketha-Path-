const CACHE = "khetha-path-v4";
const ASSETS = [
  "./", "./index.php", "./login.php", "./register.php", "./dashboard.php",
  "./my-path.php", "./what-if.php", "./ask.html", "./advice.php",
  "./subject-chooser.php", "./questionnaire.php", "./questionnaire-report.php",
  "./subject-choice-report.php", "./contact-advisor.php",
  "./assets/css/style.css", "./assets/js/app.js", "./assets/navbar.php",
  "./assets/images/khetha-logo.png", "./manifest.json"
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