<?php
session_start();
require_once __DIR__ . '/subject-choice-data.php';

$type = $_GET['type'] ?? '';
if (!sc_is_valid_type($type) || empty($_SESSION['subject_choice']['done'][$type])) {
    header('Location: subject-choice.php');
    exit;
}

$types      = sc_questionnaire_types();
$meta       = $types[$type];
$answers    = $_SESSION['subject_choice']['answers'][$type] ?? [];
$ranked     = sc_score_questionnaire($type, $answers);
$categories = sc_categories();
$top3       = array_slice($ranked, 0, 3);

$done = $_SESSION['subject_choice']['done'] ?? [];
$nextType = null;
foreach (array_keys($types) as $t) {
    if (empty($done[$t])) { $nextType = $t; break; }
}
$allDone = $nextType === null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($meta['label']) ?> Report — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1><?= htmlspecialchars($meta['label']) ?> report</h1>
        <p class="muted">Based on your answers, here are the study fields that came out strongest for this questionnaire.</p>
      </div>
    </div>

    <section class="panel path-panel">
      <div class="panel-title">
        <span>&#9733;</span>
        <div><small>YOUR TOP STUDY FIELDS</small><h2>Where you scored highest</h2></div>
      </div>

      <?php foreach ($top3 as $r): ?>
        <div class="field-bar">
          <div class="bar-label"><span><?= htmlspecialchars($r['label']) ?></span><small><?= $r['percent'] ?>%</small></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $r['percent'] ?>%"></div></div>
          <div class="bar-subjects">Linked subjects: <?= htmlspecialchars(implode(', ', $categories[$r['key']]['subjects'])) ?></div>
        </div>
      <?php endforeach; ?>
    </section>

    <div id="advisorPrompt" class="advisor-prompt">
      <div>
        <b>Want to talk this through?</b>
        <p>A Career Advisor can help you make sense of this result — this is optional, not required to continue.</p>
      </div>
      <div class="advisor-actions">
        <button class="ghost-btn" type="button" onclick="document.getElementById('advisorPrompt').style.display='none'">Not now</button>
        <a class="primary-btn" href="contact-advisor.php">Contact an Advisor</a>
      </div>
    </div>

    <div class="wizard-nav" style="max-width:640px;margin:26px auto 0">
      <a class="ghost-btn" href="subject-choice.php">Back to hub</a>
      <?php if ($allDone): ?>
        <a class="primary-btn" href="subject-choice-report.php">View combined report</a>
      <?php else: ?>
        <a class="primary-btn" href="questionnaire.php?type=<?= urlencode($nextType) ?>">Next: <?= htmlspecialchars($types[$nextType]['label']) ?></a>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
