document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("askForm");
  if (form) form.addEventListener("submit", e => {
    e.preventDefault();
    const input = document.getElementById("question");
    if (input.value.trim()) ask(input.value.trim());
  });

  document.querySelectorAll(".scenario").forEach(btn => {
    btn.addEventListener("click", () => adaptPath(btn.dataset.scenario));
  });

  updateNetworkStatus();
});

function addMessage(text, type="ai", tag="") {
  const box = document.getElementById("messages");
  if (!box) return;
  const div = document.createElement("div");
  div.className = "bubble " + type;
  div.innerHTML = (tag ? `<small class="message-tag">${tag}</small>` : "") + escapeHtml(text).replace(/\n/g,"<br>");
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function ask(question) {
  const input = document.getElementById("question");
  addMessage(question, "user");
  if (input) input.value = "";
  fetch("ask-api.php", {
    method:"POST",
    headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:"question="+encodeURIComponent(question)
  }).then(r=>r.json()).then(data => addMessage(data.answer, "ai", data.tag))
    .catch(() => addMessage("You appear to be offline. Your saved journey still works, but this live companion response needs a connection."));
}

function adaptPath(scenario) {
  const result = document.getElementById("adapterResult");
  const title = document.getElementById("resultTitle");
  const text = document.getElementById("resultText");
  const cards = document.getElementById("routeCards");
  if (!result) return;

  const data = {
    notqualify: {
      title:"Your goal can have more than one route.",
      text:"Instead of stopping at the first requirement, Khetha can help you compare adjacent qualifications and progression routes that continue toward a related career goal.",
      routes:["Alternative qualification route","Related career route","Progression / bridging route"]
    },
    subjects: {
      title:"Your pathway changes with your subjects.",
      text:"Khetha can flag where subject requirements matter, then guide you toward routes that fit your updated subject profile rather than showing you the same list.",
      routes:["Re-check subject requirements","Explore compatible qualifications","Review related careers"]
    },
    connectivity: {
      title:"Your journey should not disappear when your data does.",
      text:"The mobile experience can keep key pathway information, saved careers and next actions available offline, then sync when connectivity returns.",
      routes:["Saved journey offline","Low-data content mode","Sync when connected"]
    },
    provider: {
      title:"You can compare study routes.",
      text:"Khetha can keep the career goal fixed while allowing the learner to compare different qualification and provider options.",
      routes:["Compare qualifications","Compare providers","Save a preferred route"]
    }
  }[scenario];

  title.textContent = data.title;
  text.textContent = data.text;
  cards.innerHTML = data.routes.map((r,i)=>`<div class="route-card"><b>0${i+1}</b><span>${escapeHtml(r)}</span><em>Explore →</em></div>`).join("");
  result.classList.remove("hidden");
  result.scrollIntoView({behavior:"smooth", block:"start"});
}

function markComplete(button) {
  button.textContent = "Explored ✓";
  button.classList.add("completed");
}

function updateNetworkStatus() {
  const el = document.getElementById("networkStatus");
  if (!el) return;
  const update = () => {
    el.textContent = navigator.onLine ? "● Connected" : "● Offline mode";
    el.classList.toggle("offline", !navigator.onLine);
  };
  update();
  window.addEventListener("online", update);
  window.addEventListener("offline", update);
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

