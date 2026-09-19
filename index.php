<?php require_once __DIR__ . '/assets/lang.php'; ?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title>Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-first-page">
<?php include __DIR__ . '/assets/lang-links.php'; ?>
<main class="auth-first-shell">
  <section class="auth-first-brand">
    <div class="logo-halo"></div>
    <div class="auth-brand-stack"><img class="auth-first-logo" src="assets/images/khetha-logo.png" alt="Khetha"><span class="auth-brand-divider"></span><img class="auth-first-gov" src="assets/images/dhet-official-logo.png" alt="Department of Higher Education and Training"></div>
    <p class="auth-first-tagline"><?= t('Make the right choice. Decide your future.') ?></p>
    <div class="journey-line">
      <span><?= t('Discover') ?></span><i></i><span><?= t('Decide') ?></span><i></i><span><?= t('Do') ?></span>
    </div>
  </section>

  <section class="auth-first-card">
    <div class="eyebrow"><?= t('WELCOME TO KHETHA') ?></div>
    <h1><?= t('Your future starts with one choice.') ?></h1>
    <p class="auth-first-copy"><?= t('Create your profile or sign in to continue your personalised career journey.') ?></p>

    <div class="auth-first-actions">
      <a class="primary-btn auth-choice" href="register.php">
        <span><b><?= t('Create Account') ?></b><small><?= t('Start your personalised journey') ?></small></span>
        <strong>→</strong>
      </a>
      <a class="secondary-auth-choice" href="login.php">
        <span><b><?= t('Sign In') ?></b><small><?= t('Continue your saved journey') ?></small></span>
        <strong>→</strong>
      </a>
    </div>

    <div class="trust-strip">
      <span>🔒 <?= t('Secure profile') ?></span>
      <span>📱 <?= t('Mobile-first') ?></span>
      <span>📥 <?= t('Low-data ready') ?></span>
    </div>
  </section>
</main>
<footer class="auth-first-footer">DHET • <?= t('Khetha Career Guidance Companion') ?></footer>
</body>
</html>
