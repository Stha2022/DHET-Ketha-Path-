// Registers the service worker, warms the offline cache after the learner
// reaches their dashboard, and offers an "Install" prompt.
(() => {
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker.register("service-worker.js")
        .then(() => navigator.serviceWorker.ready)
        .then(reg => {
          // The dashboard only loads for a signed-in learner, so this is the
          // right moment to save their pages for offline use.
          if (/dashboard\.php$/.test(location.pathname) && navigator.onLine && reg.active) {
            reg.active.postMessage({ type: "warm" });
          }
        })
        .catch(() => {});
    });
  }

  const standalone = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone;
  if (standalone) return;

  const pill = (text, onClick) => {
    const b = document.createElement("button");
    b.type = "button";
    b.textContent = text;
    b.style.cssText = "position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:2000;" +
      "background:#00a99d;color:#fff;border:0;border-radius:999px;padding:12px 22px;font:700 14px system-ui,sans-serif;" +
      "box-shadow:0 10px 30px rgba(16,42,67,.25);cursor:pointer;max-width:90vw";
    b.addEventListener("click", () => { onClick(); b.remove(); });
    document.body.appendChild(b);
    return b;
  };

  // Android / desktop Chrome: use the browser's own install prompt.
  let deferred = null;
  window.addEventListener("beforeinstallprompt", e => {
    e.preventDefault();
    deferred = e;
    pill("Install Khetha on your phone", () => { deferred.prompt(); deferred = null; });
  });

  // iOS Safari has no install prompt; show the manual steps once.
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  let seen = false;
  try { seen = localStorage.getItem("khetha-ios-hint") === "1"; } catch (e) {}
  if (ios && !seen) {
    window.addEventListener("load", () => pill("Install: tap Share, then Add to Home Screen", () => {
      try { localStorage.setItem("khetha-ios-hint", "1"); } catch (e) {}
    }));
  }
})();
