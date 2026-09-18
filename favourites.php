<?php session_start(); require_once __DIR__ . '/assets/lang.php'; require_once __DIR__ . '/includes/profile.php'; $n = profile_get()['name'] ?: t('Learner'); ?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Favourites') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<main class="dashboard narrow">
  <p class="eyebrow"><?= t('SAVED JOURNEY') ?></p>
  <h1><?= t('{name}’s favourites', ['name' => $n]) ?></h1>
  <div class="empty-state">
    <span>♡</span>
    <b><?= t('Your saved options will appear here.') ?></b>
    <p><?= t('Save careers, qualifications and providers to revisit.') ?></p>
    <a class="primary-btn center" href="directories.php"><?= t('Start browsing →') ?></a>
  </div>
</main>
</body>
</html>
