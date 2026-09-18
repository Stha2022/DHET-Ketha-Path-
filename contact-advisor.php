<?php
session_start();
require_once __DIR__ . '/assets/lang.php';
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$topics = [
    'subject_choice' => t('Subject Choice'),
    'career_decision' => t('Career Decision'),
    'job_fit' => t('Job Fit'),
    'general' => t('General question'),
];

$sent  = false;
$errors = [];
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = $_POST['topic'] ?? '';
    $note  = trim($_POST['note'] ?? '');
    $old   = ['topic' => $topic, 'note' => $note];

    if (!array_key_exists($topic, $topics)) {
        $errors['topic'] = t('Please choose a topic.');
    }

    if (empty($errors)) {
        // Mocked — no real backend/advisor routing yet.
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('Contact a Career Advisor') ?> — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1><?= t('Contact a Career Advisor') ?></h1>
        <p class="muted"><?= t('Send a request and a Career Advisor will follow up with you. This is a prototype flow — no request is actually sent yet.') ?></p>
      </div>
    </div>

    <?php if ($sent): ?>
      <section class="panel path-panel">
        <div class="panel-title">
          <span>&#9989;</span>
          <div><small><?= t('REQUEST SENT') ?></small><h2><?= t('Thanks — your request is in.') ?></h2></div>
        </div>
        <p class="muted"><?= t('A Career Advisor will be in touch about <b>{topic}</b>.', ['topic' => html_entity_decode($topics[$old['topic']])]) ?><?= $old['note'] !== '' ? ' ' . t('Your note has been included.') : '' ?></p>
        <a class="ghost-btn" href="dashboard.php" style="display:inline-block;margin-top:16px"><?= t('Back to dashboard') ?></a>
      </section>
    <?php else: ?>
      <form class="wizard-panel" method="post" action="contact-advisor.php" style="max-width:560px">
        <div class="q-block">
          <div class="mini-label"><?= t('What would you like to talk about?') ?></div>
          <select name="topic">
            <option value=""><?= t('Choose a topic…') ?></option>
            <?php foreach ($topics as $val => $label): ?>
              <option value="<?= $val ?>" <?= (($old['topic'] ?? '') === $val) ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($errors['topic'])): ?><div class="field-error" style="display:block"><?= $errors['topic'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label"><?= t('Anything you’d like to add? (optional)') ?></div>
          <textarea name="note" rows="4" placeholder="<?= t('e.g. I’m torn between two study fields...') ?>" style="width:100%;padding:13px 14px;border:1px solid var(--line);border-radius:12px;font:inherit;outline:none;resize:vertical"><?= htmlspecialchars($old['note'] ?? '') ?></textarea>
        </div>
        <div class="wizard-nav">
          <a class="ghost-btn" href="dashboard.php"><?= t('Cancel') ?></a>
          <button type="submit" class="primary-btn"><?= t('Send request') ?></button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
