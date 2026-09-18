<?php session_start(); require_once __DIR__ . '/assets/lang.php'; ?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Career Advice') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<main class="dashboard narrow">
  <p class="eyebrow"><?= t('HUMAN SUPPORT') ?></p>
  <h1><?= t('Sometimes you need a person.') ?></h1>
  <p class="lead"><?= t('Keep official career advice, events and human practitioner contact easy to reach.') ?></p>
  <div class="support-grid">
    <div class="support-card"><b><?= t('Find a Career Advisor') ?></b><p><?= t('Find advice and practitioner channels.') ?></p></div>
    <a class="support-card" href="contact-advisor.php" style="display:block;color:inherit"><b><?= t('Khetha Contact') ?></b><p><?= t('Send a request and a Career Advisor will follow up with you.') ?></p></a>
    <div class="support-card"><b><?= t('Events') ?></b><p><?= t('Discover career guidance events.') ?></p></div>
  </div>
</main>
</body>
</html>
