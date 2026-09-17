<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['name'] = trim($_POST['name'] ?? 'Learner');
    $_SESSION['subjects'] = $_POST['subjects'] ?? [];
    $_SESSION['interest'] = trim($_POST['interest'] ?? '');
    $_SESSION['grade'] = $_POST['grade'] ?? 'Grade 11';
    header('Location: my-path.php');
    exit;
}
$name = $_SESSION['name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#102a43">
<title>Khetha Path</title>
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="splash-page">

<!-- Animated startup screen -->
<section id="splashScreen" class="splash-screen" aria-label="Khetha Path startup">
  <div class="splash-glow"></div>
  <div class="splash-content">
    <div class="logo-stage">
      <div class="logo-ring ring-one"></div>
      <div class="logo-ring ring-two"></div>
      <img class="splash-logo" src="assets/images/khetha-logo.png"
           alt="Khetha — Make the right choice. Decide your future.">
    </div>
    <p class="splash-tagline">Your career. Your journey. Your next step.</p>
    <div class="loading-track"><span></span></div>
    <p class="loading-text">Preparing your journey...</p>
    <button id="enterBtn" class="enter-btn">Start My Journey <span>→</span></button>
  </div>
  <div class="splash-footer">DHET • Khetha Career Guidance Companion</div>
</section>

<!-- Main welcome experience -->
<div id="welcomeApp" class="welcome-app" aria-hidden="true">
<header class="topbar">
  <div class="brand"><img class="khetha-logo" src="assets/images/khetha-logo.png" alt="Khetha"></div>
  <div class="top-actions">
    <button id="installBtn" class="install-btn" hidden>Install App</button>
    <span class="status-pill">● Low-data ready</span>
  </div>
</header>

<main class="hero-grid">
<section class="hero-copy">
  <p class="eyebrow">CAREER GUIDANCE • MOBILE-FIRST • AI-ASSISTED</p>
  <h1>Don’t just choose a career.<br><span>Build your path.</span></h1>
  <p class="lead">Khetha Path turns trusted career guidance into a personal journey — helping you understand where you are, what is possible, and what to do next.</p>
  <div class="differentiator">
    <div class="icon">✦</div>
    <div>
      <strong>Your path adapts with you.</strong>
      <p>Change a subject, result or pathway scenario and Khetha shows how your route could adapt.</p>
    </div>
  </div>
</section>

<section class="companion-card">
  <div class="companion-head">
    <div class="avatar">K</div>
    <div><small>KHETHA COMPANION</small><strong>Your journey starts here</strong></div>
    <span class="online-dot">●</span>
  </div>
  <div class="chat-window">
    <div class="bubble ai">Hi! 👋 I’m Khetha. I’ll help you build a career path — not just give you a career name.</div>
    <div class="bubble ai">First, what should I call you?</div>
    <form method="POST" id="startForm">
      <input name="name" id="name" placeholder="e.g. Lindi" required value="<?=htmlspecialchars($name)?>">
      <div class="mini-label">What level are you currently in?</div>
      <select name="grade" required>
        <option>Grade 9</option><option>Grade 10</option><option selected>Grade 11</option><option>Grade 12</option><option>Post-school</option>
      </select>
      <div class="mini-label">Which subjects are you taking?</div>
      <div class="chips">
        <?php foreach (['Mathematics','Physical Sciences','IT','Accounting','Life Sciences','Business Studies'] as $s): ?>
          <label class="chip"><input type="checkbox" name="subjects[]" value="<?=$s?>"><span><?=$s?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="mini-label">What are you curious about?</div>
      <input name="interest" placeholder="e.g. building apps, solving problems, helping people" required>
      <button class="primary-btn" type="submit">Start My Journey →</button>
    </form>
  </div>
  <div class="privacy-note">🔒 Prototype uses demo data. Production version connects to approved NCAP/DHET sources with consent and secure APIs.</div>
</section>
</main>
</div>

<script src="assets/js/app.js"></script>
<script>
(function () {
  const splash = document.getElementById("splashScreen");
  const app = document.getElementById("welcomeApp");
  const enter = document.getElementById("enterBtn");

  // Small startup animation: automatically enables the button after the logo reveal.
  setTimeout(() => enter.classList.add("ready"), 1700);

  enter.addEventListener("click", () => {
    splash.classList.add("exit");
    setTimeout(() => {
      splash.style.display = "none";
      app.classList.add("show");
      app.setAttribute("aria-hidden", "false");
      document.getElementById("name")?.focus();
    }, 650);
  });
})();
</script>
</body>
</html>