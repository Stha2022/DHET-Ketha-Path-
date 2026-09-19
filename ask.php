<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
kp_require_auth();
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Ask Khetha') ?> — AI</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<div class="app-shell">
<main id="main-content" class="chat-page">
<section class="chat-card">
 <div class="companion-head large"><div class="avatar">K</div><div><small><?= t('KHETHA COMPANION') ?></small><strong><?= t('Ask about your path') ?></strong></div></div>
 <div id="messages" class="messages">
   <div class="bubble ai"><?= t('Hi! I’m Khetha. Ask me about your <b>next step</b>, your <b>pathway</b>, or what happens if your circumstances change.') ?></div>
   <div class="suggestions">
    <button onclick="ask('What can I study if I like computers?')"><?= t('I like computers') ?></button>
    <button onclick="ask('What if I don’t qualify?')"><?= t('What if I don’t qualify?') ?></button>
    <button onclick="ask('Why did you suggest this path?')"><?= t('Why this path?') ?></button>
   </div>
 </div>
 <form id="askForm" class="ask-form">
   <input id="question" placeholder="<?= t('Ask Khetha something...') ?>" autocomplete="off" required>
   <button class="primary-btn" type="submit"><?= t('Ask →') ?></button>
 </form>
 <p class="source-note"><?= t('Prototype AI companion: responses are intentionally controlled for the demo. Production should use approved NCAP/DHET knowledge, retrieval, consent, logging, explainability and human escalation.') ?></p>
</section>
</main>
</div>
<script>window.KP_I18N = { offline: <?= json_encode(html_entity_decode(t('You appear to be offline. Your saved journey still works, but this live companion response needs a connection.')), JSON_UNESCAPED_UNICODE) ?> };</script>
<script src="assets/js/app.js"></script>
</body>
</html>