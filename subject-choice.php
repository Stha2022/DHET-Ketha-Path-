<?php
session_start();
require_once __DIR__ . '/subject-choice-data.php';

$types = sc_questionnaire_types();
$done  = $_SESSION['subject_choice']['done'] ?? [];
$completedCount = 0;
foreach (array_keys($types) as $t) {
    if (!empty($done[$t])) $completedCount++;
}
$allDone = $completedCount === count($types);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subject Choice — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1>Subject Choice</h1>
        <p class="muted">NCAP's Self Exploration questionnaires help you understand what study fields could suit you. Complete all three below to unlock your combined report.</p>
      </div>
    </div>

    <div class="journey-strip">
      <?php $i = 1; foreach ($types as $key => $meta): ?>
        <div class="journey-step <?= !empty($done[$key]) ? 'active' : '' ?>"><b><?= $i ?></b><span><?= htmlspecialchars($meta['label']) ?></span></div>
        <?php if ($i < count($types)): ?><div class="journey-line"></div><?php endif; ?>
      <?php $i++; endforeach; ?>
      <div class="journey-line"></div>
      <div class="journey-step <?= $allDone ? 'active' : '' ?>"><b><?= $i ?></b><span>Combined Report</span></div>
    </div>

    <div class="qcard-grid">
      <?php $n = 1; foreach ($types as $key => $meta): $isDone = !empty($done[$key]); ?>
        <div class="qcard">
          <div class="q-num"><?= $n ?></div>
          <span class="status-pill <?= $isDone ? '' : 'offline' ?>"><?= $isDone ? 'Completed' : 'Not started' ?></span>
          <h3><?= htmlspecialchars($meta['label']) ?></h3>
          <p><?= htmlspecialchars($meta['desc']) ?></p>
          <?php if ($isDone): ?>
            <a class="ghost-btn" href="questionnaire-report.php?type=<?= urlencode($key) ?>">View report</a>
          <?php else: ?>
            <a class="primary-btn" href="questionnaire.php?type=<?= urlencode($key) ?>">Start questionnaire</a>
          <?php endif; ?>
        </div>
      <?php $n++; endforeach; ?>
    </div>

    <section class="panel path-panel" style="margin-top:6px">
      <div class="panel-title">
        <span>&#9733;</span>
        <div><small>SUBJECT CHOICE</small><h2>Combined report</h2></div>
      </div>
      <?php if ($allDone): ?>
        <p class="muted">You've completed all three questionnaires. See your top study fields, suggested subjects and where you could study them.</p>
        <a class="primary-btn" href="subject-choice-report.php">View combined report</a>
      <?php else: ?>
        <p class="muted">Complete all <?= count($types) ?> questionnaires above (<?= $completedCount ?>/<?= count($types) ?> done) to unlock your combined study-field report.</p>
        <button class="primary-btn" type="button" disabled style="opacity:.5;cursor:not-allowed">View combined report</button>
      <?php endif; ?>
    </section>

    <div class="advisor-prompt">
      <div>
        <b>Not sure about any of this?</b>
        <p>A Career Advisor can talk through your questionnaire results and what they mean for your subject choices.</p>
      </div>
      <div class="advisor-actions">
        <a class="ghost-btn" href="contact-advisor.php">Contact a Career Advisor</a>
      </div>
    </div>

    <?php if ($completedCount > 0): ?>
      <form method="post" action="reset-subject-choice.php" style="margin-top:18px" onsubmit="return confirm('Reset all Subject Choice questionnaires? This clears your saved answers and reports.');">
        <button type="submit" class="ghost-btn" style="color:#c0392b;border-color:#f3c9c2">Reset assessment (clear all 3 questionnaires)</button>
      </form>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
