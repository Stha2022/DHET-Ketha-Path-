<?php
// Demo mode: no database, so there's no real account to check against.
// Any well-formed email + non-empty password signs in with a fresh demo
// profile (name guessed from the email) — nothing is persisted.
session_start();
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/account.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
        $error = t('Please enter your email and password.');
    } else {
        session_regenerate_id(true);

        $guessedName = ucwords(str_replace(['.', '_', '+'], ' ', explode('@', $email)[0]));

        $_SESSION['user'] = ['id' => 1, 'name' => $guessedName, 'email' => $email];
        // A fresh blank profile: only the name is guessed. Grade and the rest
        // are left empty for the learner to fill in, not invented.
        profile_reset();
        profile_update(['name' => $guessedName]);

        // Demo mode accepts any password, so whatever was typed here becomes the
        // current password for this session — hashed, so the account page can
        // genuinely re-authenticate before an email or password change.
        account_set_credentials(1, $guessedName, $email, $password);

        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Sign in') ?> — Khetha</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<header class="topbar">
  <a class="brand" href="index.php"><img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha"></a>
  <?php include __DIR__ . '/assets/lang-links.php'; ?>
  <a class="ghost-btn" href="register.php"><?= t('Create account') ?></a>
</header>

<main class="auth-layout">
<section class="auth-intro">
  <p class="eyebrow"><?= t('WELCOME BACK') ?></p>
  <h1><?= t('Your journey is waiting.') ?></h1>
  <p class="lead"><?= t('Sign in to continue with your saved profile, assessments and pathway.') ?></p>
</section>

<section class="auth-card">
  <div class="step-label">→ &nbsp; <?= t('SIGN IN') ?></div>

  <?php if ($error): ?>
    <div class="error-box"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <label><?= t('Email') ?>
      <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </label>
    <label><?= t('Password') ?>
      <input type="password" name="password" required>
    </label>
    <button class="primary-btn" type="submit"><?= t('Continue My Journey →') ?></button>
  </form>

  <div class="demo-note">🧪 <?= t('Demo mode — no database. Any email and password will sign you in with a fresh session; nothing is checked or stored. Prefer a personalised profile? <a href="register.php">Create one</a> instead.') ?></div>
  <p class="auth-switch"><?= t('New to Khetha?') ?> <a href="register.php"><?= t('Create your profile') ?></a></p>
</section>
</main>
</body>
</html>