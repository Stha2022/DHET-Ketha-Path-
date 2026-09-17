<?php
session_start();
require_once __DIR__ . '/subject-choice-data.php';

$types = sc_questionnaire_types();
$done  = $_SESSION['subject_choice']['done'] ?? [];
foreach (array_keys($types) as $t) {
    if (empty($done[$t])) {
        header('Location: subject-choice.php');
        exit;
    }
}

$allAnswers = $_SESSION['subject_choice']['answers'] ?? [];
$top3       = sc_combined_report($allAnswers);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subject Choice Report — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1>Your Subject Choice report</h1>
        <p class="muted">Combining your Interests, Career Abilities and Employability Skills questionnaires, here are your top matching study fields.</p>
      </div>
    </div>

    <div class="journey-strip">
      <?php $i = 1; foreach ($types as $meta): ?>
        <div class="journey-step active"><b><?= $i ?></b><span><?= htmlspecialchars($meta['label']) ?></span></div>
        <div class="journey-line"></div>
      <?php $i++; endforeach; ?>
      <div class="journey-step active"><b><?= $i ?></b><span>Combined Report</span></div>
    </div>

    <?php foreach ($top3 as $idx => $r): ?>
      <section class="panel path-panel" style="margin-top:18px">
        <div class="panel-title">
          <span>&#9733;</span>
          <div>
            <small><?= $idx === 0 ? 'BEST MATCH' : ($idx === 1 ? 'ALSO STRONG' : 'WORTH EXPLORING') ?></small>
            <h2><?= htmlspecialchars($r['label']) ?></h2>
          </div>
        </div>

        <div class="field-bar">
          <div class="bar-label"><span>Overall match</span><small><?= $r['percent'] ?>%</small></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $r['percent'] ?>%"></div></div>
        </div>

        <div class="mini-label">Suggested matric subjects</div>
        <div class="chips">
          <?php foreach ($r['subjects'] as $subj): ?>
            <span class="chip"><span><?= htmlspecialchars($subj) ?></span></span>
          <?php endforeach; ?>
        </div>

        <div class="mini-label">Where you could study this (example institutions)</div>
        <?php foreach ($r['institutions'] as $inst): ?>
          <div class="inst-card">
            <b><?= htmlspecialchars($inst['name']) ?></b>
            <span><?= htmlspecialchars($inst['qualification']) ?></span>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <div class="insight" style="margin-top:22px">
      <p>Institution and qualification names above are illustrative example data for this prototype, not a live NCAP listing — search these study fields on NCAP's course finder for current, verified programmes and admission requirements.</p>
    </div>

    <div class="advisor-prompt" style="margin-top:22px">
      <div>
        <b>Want a second opinion?</b>
        <p>A Career Advisor can help you weigh these study fields against your own plans — optional, not required.</p>
      </div>
      <div class="advisor-actions">
        <a class="primary-btn" href="contact-advisor.php">Contact a Career Advisor</a>
      </div>
    </div>

    <a href="subject-choice.php" class="ghost-btn" style="display:inline-block;margin-top:22px">Back to Subject Choice</a>
  </main>
</div>
</body>
</html>
