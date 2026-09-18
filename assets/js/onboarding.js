// Registration, one question at a time.
//
// Progressive enhancement: register.php renders every step visible inside one
// ordinary form, so with JavaScript off it still works as a single page that the
// browser validates and posts as it always did. This file hides all but the
// current step and validates each one before letting the learner move on. The
// server (register.php) re-checks everything regardless.
(() => {
  const form = document.getElementById("onbForm");
  if (!form) return;

  const steps = [...form.querySelectorAll(".onb-step")];
  if (steps.length < 2) return;

  const t = window.KP_ONB || {};
  const say = (key, vars) => String(t[key] || key).replace(/\{(\w+)\}/g, (_, k) => (vars && vars[k] !== undefined ? vars[k] : ""));
  const $ = id => document.getElementById(id);
  const label = $("onbLabel"), barWrap = $("onbBarWrap"), bar = $("onbBar");
  const backBtn = $("onbBack"), nextBtn = $("onbNext"), submitBtn = $("onbSubmit"), errorBox = $("onbError");

  // The browser can't validate a field it can't see, so take validation over.
  form.noValidate = true;
  barWrap.hidden = false;
  backBtn.hidden = false;
  nextBtn.hidden = false;

  const val = name => (form.elements[name] ? form.elements[name].value.trim() : "");
  const checked = name => [...form.querySelectorAll(`input[name="${name}"]:checked`)];

  // One rule per step, in the order the steps appear.
  const rules = [
    () => (val("name") === "" ? "name" : null),
    () => (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val("email")) ? null : "email"),
    () => (val("password").length >= 8 ? null : "password"),
    () => (checked("grade").length ? null : "grade"),
    () => (checked("subjects[]").length ? null : "subjects"),
    () => (checked("interests[]").length >= 3 ? null : "interests"),
    () => (form.elements.consent.checked ? null : "consent"),
  ];

  let current = 0;

  // After a failed server-side submit, open at the first step that is still wrong
  // rather than making the learner walk through the ones they already answered.
  if (document.querySelector(".error-box")) {
    const firstBad = rules.findIndex(r => r() !== null);
    current = firstBad === -1 ? steps.length - 1 : firstBad;
  }

  function clearError() {
    errorBox.hidden = true;
    errorBox.textContent = "";
  }

  function showError(key) {
    errorBox.textContent = say(key);
    errorBox.hidden = false;
  }

  function show(n, focus) {
    current = Math.max(0, Math.min(n, steps.length - 1));
    steps.forEach((s, i) => { s.hidden = i !== current; });
    const last = current === steps.length - 1;
    label.textContent = say("step", { n: current + 1, total: steps.length });
    bar.style.width = Math.round(((current + 1) / steps.length) * 100) + "%";
    backBtn.disabled = current === 0;
    nextBtn.hidden = last;
    submitBtn.hidden = !last;
    clearError();
    if (focus !== false) {
      const field = steps[current].querySelector("input:not([type=checkbox]):not([type=radio])");
      if (field) field.focus({ preventScroll: true });
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function advance() {
    const bad = rules[current]();
    if (bad) { showError(bad); return; }
    if (current < steps.length - 1) show(current + 1);
  }

  nextBtn.addEventListener("click", advance);
  backBtn.addEventListener("click", () => show(current - 1));

  // Enter moves on instead of submitting a half-finished form.
  form.addEventListener("keydown", e => {
    if (e.key !== "Enter" || e.target.tagName === "TEXTAREA") return;
    if (current < steps.length - 1) { e.preventDefault(); advance(); }
  });

  // Picking a grade is a single choice, so move straight on.
  form.querySelectorAll('input[name="grade"]').forEach(r => {
    r.addEventListener("change", () => { clearError(); setTimeout(() => show(current + 1), 180); });
  });

  // Live "n picked" counts on the multi-select steps.
  const counters = [["subjects[]", 'strong[data-count="subjects"]'], ["interests[]", 'strong[data-count="interests"]']];
  counters.forEach(([name, sel]) => {
    const out = form.querySelector(sel);
    if (!out) return;
    const update = () => { out.textContent = say("picked", { n: checked(name).length }); };
    form.querySelectorAll(`input[name="${name}"]`).forEach(c => c.addEventListener("change", () => { clearError(); update(); }));
    update();
  });

  form.querySelectorAll("input").forEach(i => i.addEventListener("input", clearError));

  // Last line of defence in the browser: never post a step that fails its rule.
  form.addEventListener("submit", e => {
    const bad = rules.findIndex(r => r() !== null);
    if (bad !== -1) { e.preventDefault(); show(bad); showError(rules[bad]()); }
  });

  show(current, false);
})();
