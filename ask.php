<?php
session_start();
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title>Ask Khetha — AI Companion</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<div class="app-shell">
<main class="chat-page">
<section class="chat-card">
 <div class="companion-head large"><div class="avatar">K</div><div><small>KHETHA COMPANION</small><strong>Ask about your path</strong></div></div>
 <div id="messages" class="messages">
   <div class="bubble ai">Hi! I’m Khetha. Ask me about your <b>next step</b>, your <b>pathway</b>, or what happens if your circumstances change.</div>
   <div class="suggestions">
    <button onclick="ask('What can I study if I like computers?')">I like computers</button>
    <button onclick="ask('What if I don’t qualify?')">What if I don’t qualify?</button>
    <button onclick="ask('Why did you suggest this path?')">Why this path?</button>
   </div>
 </div>
 <form id="askForm" class="ask-form">
   <input id="question" placeholder="Ask Khetha something..." autocomplete="off" required>
   <button class="primary-btn" type="submit">Ask →</button>
 </form>
 <p class="source-note">Prototype AI companion: responses are intentionally controlled for the demo. Production should use approved NCAP/DHET knowledge, retrieval, consent, logging, explainability and human escalation.</p>
</section>
</main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>