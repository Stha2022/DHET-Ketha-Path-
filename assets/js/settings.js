// Settings page behaviour. Device preferences live in localStorage under
// "khetha-settings"; assets/js/pwa.js reads the same key (data saver).
(() => {
  const KEY = "khetha-settings";
  const DEFAULTS = {
    notify: false,
    notifyDeadlines: true,
    notifyAssessments: true,
    notifyTips: false,
    dataSaver: false,
  };
  const $ = id => document.getElementById(id);
  const i18n = JSON.parse($("settings-i18n").textContent);

  const load = () => {
    try { return Object.assign({}, DEFAULTS, JSON.parse(localStorage.getItem(KEY) || "{}")); }
    catch (e) { return Object.assign({}, DEFAULTS); }
  };
  const save = s => { try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) {} };
  let settings = load();

  const say = (id, text) => { $(id).textContent = text; };

  // ---- Notifications ----
  const supported = "Notification" in window;
  const perm = () => (supported ? Notification.permission : "unsupported");

  function renderNotifications() {
    const master = $("notify");
    const p = perm();
    const on = p === "granted" && settings.notify;
    master.checked = on;
    master.disabled = !supported;

    let text = i18n.permNone;
    if (supported) {
      if (p === "denied") text = i18n.permDenied;
      else if (p === "granted") text = settings.notify ? i18n.permGranted : i18n.permOff;
      else text = i18n.permDefault;
    }
    say("notify-perm", text);

    $("notify-types").classList.toggle("on", on);
    document.querySelectorAll("[data-needs-notify]").forEach(el => { el.disabled = !on; });
    $("notify-test").hidden = !on;

    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const standalone = matchMedia("(display-mode: standalone)").matches || navigator.standalone;
    $("notify-ios").hidden = !(ios && !standalone);
  }

  $("notify").addEventListener("change", async e => {
    say("notify-status", "");
    if (!e.target.checked) {
      settings.notify = false;
      save(settings);
      return renderNotifications();
    }
    if (!supported) return renderNotifications();
    let p = Notification.permission;
    if (p === "default") {
      try { p = await Notification.requestPermission(); } catch (err) { p = Notification.permission; }
    }
    settings.notify = p === "granted";
    save(settings);
    renderNotifications();
  });

  $("notify-test").addEventListener("click", async () => {
    const opts = { body: i18n.testBody, icon: "assets/images/icon-192.png", tag: "khetha-test" };
    try {
      const reg = "serviceWorker" in navigator ? await navigator.serviceWorker.getRegistration() : null;
      if (reg) await reg.showNotification(i18n.testTitle, opts);
      else new Notification(i18n.testTitle, opts);
      say("notify-status", i18n.testSent);
    } catch (err) {
      say("notify-status", i18n.testFailed);
    }
  });

  // ---- Plain on/off preferences ----
  document.querySelectorAll("[data-setting]").forEach(el => {
    if (el.id === "notify") return;
    el.checked = !!settings[el.dataset.setting];
    el.addEventListener("change", () => {
      settings[el.dataset.setting] = el.checked;
      save(settings);
    });
  });

  renderNotifications();
  // Permission can change in browser settings while this tab is open.
  document.addEventListener("visibilitychange", () => { if (!document.hidden) renderNotifications(); });

  // ---- Offline pages ----
  const hasSw = "serviceWorker" in navigator;

  $("offline-save").addEventListener("click", async () => {
    if (!hasSw) return say("offline-status", i18n.saveNoSw);
    if (!navigator.onLine) return say("offline-status", i18n.saveOffline);
    say("offline-status", i18n.saving);
    try {
      const reg = await navigator.serviceWorker.ready;
      navigator.serviceWorker.addEventListener("message", function done(e) {
        if (!e.data || e.data.type !== "warmed") return;
        navigator.serviceWorker.removeEventListener("message", done);
        say("offline-status", i18n.saved);
      });
      reg.active.postMessage({ type: "warm" });
    } catch (err) {
      say("offline-status", i18n.saveNoSw);
    }
  });

  $("offline-clear").addEventListener("click", async () => {
    if (!("caches" in window)) return say("offline-status", i18n.clearedNone);
    const names = (await caches.keys()).filter(n => n.startsWith("khetha-pages-"));
    await Promise.all(names.map(n => caches.delete(n)));
    say("offline-status", names.length ? i18n.cleared : i18n.clearedNone);
  });

  // ---- Privacy ----
  // "Privacy policy" links elsewhere on the page open the accordion.
  const policy = $("policy-body");
  const openPolicy = () => bootstrap.Collapse.getOrCreateInstance(policy, { toggle: false }).show();
  document.querySelectorAll("[data-open-policy]").forEach(a => a.addEventListener("click", openPolicy));
  if (location.hash === "#privacy-policy" || location.hash === "#policy") {
    openPolicy();
    $("privacy").scrollIntoView();
  }

  // Deleting also wipes what Khetha keeps in this browser. The form then
  // posts to the server, which ends the session via logout.php (that also
  // clears the service worker's saved pages).
  $("delete-form").addEventListener("submit", () => {
    try {
      Object.keys(localStorage)
        .filter(k => k.startsWith("khetha"))
        .forEach(k => localStorage.removeItem(k));
    } catch (e) {}
  });
})();
