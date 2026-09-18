<?php session_start(); require_once __DIR__ . '/assets/lang.php'; ?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Browse careers and study options') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<main class="dashboard">
  <p class="eyebrow"><?= t('BROWSE') ?></p>
  <h1><?= t('Find what comes next.') ?></h1>
  <div class="directory-tabs"><button class="active"><?= t('Careers') ?></button><button><?= t('What to Study') ?></button><button><?= t('Where to Study') ?></button></div>
  <div class="directory-list">
    <?php foreach (['Software Developer', 'Cybersecurity Analyst', 'Data Analyst', 'Web Developer'] as $x): ?>
      <div class="directory-item"><b><?= t($x) ?></b><span><?= t('View pathway →') ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="source-note"><?= t('Prototype list. Production content must synchronise with approved NCAP/DHET sources.') ?></div>
</main>
</body>
</html>
