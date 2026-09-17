<?php
session_start();

$topics = [
    'subject_choice' => 'Subject Choice',
    'career_decision' => 'Career Decision',
    'job_fit' => 'Job Fit',
    'general' => 'General question',
];

$sent  = false;
$errors = [];
$old   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $topic = $_POST['topic'] ?? '';
    $note  = trim($_POST['note'] ?? '');
    $old   = ['topic' => $topic, 'note' => $note];

    if (!array_key_exists($topic, $topics)) {
        $errors['topic'] = 'Please choose a topic.';
    }

    if (empty($errors)) {
        // Mocked — no real backend/advisor routing yet.
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contact a Career Advisor — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1>Contact a Career Advisor</h1>
        <p class="muted">Send a request and a Career Advisor will follow up with you. This is a prototype flow — no request is actually sent yet.</p>
      </div>
    </div>

    <?php if ($sent): ?>
      <section class="panel path-panel">
        <div class="panel-title">
          <span>&#9989;</span>
          <div><small>REQUEST SENT</small><h2>Thanks — your request is in.</h2></div>
        </div>
        <p class="muted">A Career Advisor will be in touch about <b><?= htmlspecialchars($topics[$old['topic']]) ?></b><?= $old['note'] !== '' ? '. Your note has been included.' : '.' ?></p>
        <a class="ghost-btn" href="subject-choice.php" style="display:inline-block;margin-top:16px">Back to Subject Choice</a>
      </section>
    <?php else: ?>
      <form class="wizard-panel" method="post" action="contact-advisor.php" style="max-width:560px">
        <div class="q-block">
          <div class="mini-label">What would you like to talk about?</div>
          <select name="topic">
            <option value="">Choose a topic&hellip;</option>
            <?php foreach ($topics as $val => $label): ?>
              <option value="<?= $val ?>" <?= (($old['topic'] ?? '') === $val) ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (!empty($errors['topic'])): ?><div class="field-error" style="display:block"><?= $errors['topic'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label">Anything you'd like to add? (optional)</div>
          <textarea name="note" rows="4" placeholder="e.g. I'm torn between two study fields..." style="width:100%;padding:13px 14px;border:1px solid var(--line);border-radius:12px;font:inherit;outline:none;resize:vertical"><?= htmlspecialchars($old['note'] ?? '') ?></textarea>
        </div>
        <div class="wizard-nav">
          <a class="ghost-btn" href="subject-choice.php">Cancel</a>
          <button type="submit" class="primary-btn">Send request</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
