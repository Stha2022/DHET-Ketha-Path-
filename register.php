<?php
// Demo mode: no database. The profile lives only in this browser session —
// nothing is persisted, so there's nothing to check for a duplicate email.
session_start();
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/account.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $grade    = trim($_POST['grade'] ?? '');
    $subjects = $_POST['subjects'] ?? [];
    $interests = $_POST['interests'] ?? [];
    $consent  = isset($_POST['consent']);

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$grade || !is_array($subjects) || count($subjects) === 0 || !$consent) {
        $error = t('Please complete all required fields, select at least one subject and accept the consent statement.');
    } else {
        session_regenerate_id(true);
        // $_SESSION['user'] is only the signed-in marker; everything the
        // learner tells us lives in the shared profile.
        $_SESSION['user'] = ['id' => 1, 'name' => $name, 'email' => $email];

        // Only real chips are accepted; anything else posted is ignored.
        $chips = [];
        foreach ((array)$interests as $v) {
            $label = is_string($v) ? kp_interest_label($v) : null;
            if ($label !== null && !in_array($label, $chips, true)) $chips[] = $label;
        }
        profile_reset();
        profile_update(['name' => $name, 'grade' => $grade, 'subjects' => $subjects, 'interests' => $chips]);

        // Hashed straight away; the plain password is never stored anywhere.
        account_set_credentials(1, $name, $email, $password);

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
<title><?= t('Create your Khetha profile') ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<header class="topbar">
  <a class="brand" href="index.php">
    <img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha">
  </a>
  <?php include __DIR__ . '/assets/lang-links.php'; ?>
  <span class="status-pill"><?= t('Your journey starts here') ?></span>
</header>

<main class="auth-layout">
<section class="auth-intro">
  <p class="eyebrow"><?= t('CREATE YOUR KHETHA PROFILE') ?></p>
  <h1><?= t('Let’s make this personal.') ?></h1>
  <p class="lead"><?= t('Your answers become the starting point for a personalised career journey.') ?></p>
  <div class="privacy-card">
    <b>🧪 <?= t('Demo mode — no database.') ?></b>
    <p><?= t('This deployment doesn’t use a database. Your profile is kept only in this browser session for the demo, and disappears when you sign out or the session expires.') ?></p>
  </div>
</section>

<section class="auth-card">
  <div class="onb-top">
    <span class="step-label" id="onbLabel"><?= t('CREATE ACCOUNT') ?> &nbsp; • &nbsp; <?= t('PERSONALISE') ?></span>
    <div class="onb-bar" id="onbBarWrap" hidden><i id="onbBar"></i></div>
  </div>

  <?php if ($error): ?>
    <div class="error-box"><?= $error ?></div>
  <?php endif; ?>

  <?php
    // One question per screen (assets/js/onboarding.js). Without JavaScript every
    // step simply stays visible and this is the same single form it always was.
    $subjectOptions = ['Mathematics','Mathematical Literacy','Physical Sciences','Life Sciences','IT','Computer Applications Technology','Accounting','Business Studies','Economics','Geography'];
    $oldSubjects = (array)($_POST['subjects'] ?? []);
    $oldInterests = (array)($_POST['interests'] ?? []);
  ?>
  <form method="POST" id="onbForm">

    <div class="onb-step" data-step="1">
      <h2 class="onb-q"><?= t('What should we call you?') ?></h2>
      <label><?= t('Full name') ?>
        <input name="name" required autocomplete="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="<?= t('e.g. Lindiwe Mokoena') ?>">
      </label>
    </div>

    <div class="onb-step" data-step="2">
      <h2 class="onb-q"><?= t('What’s your email?') ?></h2>
      <label><?= t('Email') ?>
        <input type="email" name="email" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
      </label>
    </div>

    <div class="onb-step" data-step="3">
      <h2 class="onb-q"><?= t('Create a password') ?></h2>
      <label><?= t('Password') ?>
        <input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="<?= t('At least 8 characters') ?>">
      </label>
    </div>

    <div class="onb-step" data-step="4">
      <h2 class="onb-q"><?= t('What grade are you in?') ?></h2>
      <div class="subject-grid chip-grid" id="gradeGrid">
        <?php foreach (['Grade 9','Grade 10','Grade 11','Grade 12','Post-school'] as $g): ?>
          <label class="subject-choice">
            <input type="radio" name="grade" value="<?= $g ?>" required <?= (($_POST['grade'] ?? '') === $g) ? 'checked' : '' ?>>
            <span><?= t($g) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="onb-step" data-step="5">
      <h2 class="onb-q"><?= t('Which subjects are you taking?') ?></h2>
      <p class="onb-hint"><?= t('Select all that apply') ?> <strong data-count="subjects"></strong></p>
      <div class="subject-grid">
        <?php foreach ($subjectOptions as $x): ?>
          <label class="subject-choice">
            <input type="checkbox" name="subjects[]" value="<?= htmlspecialchars($x) ?>" <?= in_array($x, $oldSubjects, true) ? 'checked' : '' ?>>
            <span><?= t($x) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="onb-step" data-step="6">
      <h2 class="onb-q"><?= t('What are you interested in?') ?></h2>
      <p class="onb-hint"><?= t('Pick at least 3. This helps Khetha personalise your starting point.') ?> <strong data-count="interests"></strong></p>
      <?php foreach (kp_interest_groups() as $group => $chips): ?>
        <div class="step-label" style="margin:6px 0 0"><?= t($group) ?></div>
        <div class="subject-grid chip-grid">
          <?php foreach (array_keys($chips) as $chip): ?>
            <label class="subject-choice">
              <input type="checkbox" name="interests[]" value="<?= htmlspecialchars($chip) ?>" <?= in_array($chip, $oldInterests, true) ? 'checked' : '' ?>>
              <span><?= t($chip) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="onb-step" data-step="7">
      <h2 class="onb-q"><?= t('One last thing') ?></h2>
      <label class="consent-row">
        <input type="checkbox" name="consent" value="1" required>
        <span><?= t('I understand that my information will be used to personalise my Khetha journey.') ?></span>
      </label>
    </div>

    <p class="onb-error" id="onbError" role="alert" hidden></p>

    <div class="onb-nav">
      <button type="button" class="ghost-btn" id="onbBack" hidden><?= t('Back') ?></button>
      <button type="button" class="primary-btn" id="onbNext" hidden><?= t('Next') ?></button>
      <button class="primary-btn" type="submit" id="onbSubmit"><?= t('Create My Khetha Profile →') ?></button>
    </div>
  </form>

  <p class="auth-switch"><?= t('Already have an account?') ?> <a href="login.php"><?= t('Sign in') ?></a></p>
</section>
</main>
<script>window.KP_ONB = <?= json_encode([
  'step' => t('Question {n} of {total}'),
  'picked' => t('{n} picked'),
  'name' => t('Please enter your name.'),
  'email' => t('Please enter a valid email address.'),
  'password' => t('Your password needs at least 8 characters.'),
  'grade' => t('Please choose your current level.'),
  'subjects' => t('Choose at least one subject.'),
  'interests' => t('Pick at least 3 interests.'),
  'consent' => t('Please accept the consent statement to continue.'),
], JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/onboarding.js" defer></script>
</body>
</html>