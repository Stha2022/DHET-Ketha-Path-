<?php
session_start();
require_once __DIR__ . '/data.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- collect + lightly validate ---
    $subjects = $_POST['subjects'] ?? [];
    $act      = $_POST['activities'] ?? [];
    $env      = $_POST['environment'] ?? '';
    $work     = $_POST['work_style'] ?? '';
    $values   = $_POST['values'] ?? [];
    $route    = $_POST['route'] ?? '';

    if (empty($subjects))         $errors['subjects'] = 'Pick at least one subject.';
    if (empty($act))              $errors['activities'] = 'Pick at least one activity.';
    if ($env === '')              $errors['environment'] = 'Choose an environment.';
    if ($work === '')             $errors['work_style'] = 'Choose a work style.';
    if (empty($values))           $errors['values'] = 'Pick at least one.';
    if ($route === '')            $errors['route'] = 'Choose a study route.';

    if (empty($errors)) {
        // Flatten every chip/radio value into one answers array for scoring
        $answers = array_merge($subjects, $act, [$env, $work], $values, ['route' => $route]);

        $_SESSION['answers']  = $answers;
        $_SESSION['results']  = khetha_compute_results(array_merge($answers, ['route' => $route]));

        header('Location: results.php');
        exit;
    }
    // fall through and re-render the form with errors + previous input
}

$old = $_POST ?? [];
function checked_chip($group, $value, $old) {
    $vals = $old[$group] ?? [];
    return is_array($vals) && in_array($value, $vals, true) ? 'checked' : '';
}
function checked_radio($group, $value, $old) {
    return (isset($old[$group]) && $old[$group] === $value) ? 'checked' : '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Career Choice &amp; Job Fit — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">K</span> Khetha<b>Path</b></a>
    <div class="top-actions"><span class="status-pill">Assessment</span></div>
  </header>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1>Career Choice &amp; Job Fit</h1>
        <p class="muted">A few quick questions — takes about 3 minutes. Your answers shape the personalised suggestions on the next screen.</p>
      </div>
    </div>

    <div class="journey-strip" id="journeyStrip">
      <div class="journey-step active" data-step="1"><b>1</b><span>Career Choice</span></div>
      <div class="journey-line"></div>
      <div class="journey-step" data-step="2"><b>2</b><span>Job Fit</span></div>
      <div class="journey-line"></div>
      <div class="journey-step" data-step="3"><b>3</b><span>Results</span></div>
    </div>

    <form class="wizard-panel" method="post" action="assessment.php" id="wizardForm" novalidate>

      <!-- STEP 1: Career choice -->
      <section class="step-panel active" data-step="1">
        <div class="q-block">
          <div class="mini-label">Which subjects do you enjoy most?</div>
          <div class="q-help">Pick as many as apply.</div>
          <div class="chips">
            <?php
            $subjectOptions = [
              'subject_maths' => 'Mathematics', 'subject_physical_sci' => 'Physical Sciences',
              'subject_life_sci' => 'Life Sciences', 'subject_business' => 'Business Studies',
              'subject_accounting' => 'Accounting', 'subject_languages' => 'Languages',
              'subject_arts' => 'Arts & Design', 'subject_technical' => 'Technical / EGD',
              'subject_agriculture' => 'Agriculture',
            ];
            foreach ($subjectOptions as $val => $label): ?>
              <label class="chip">
                <input type="checkbox" name="subjects[]" value="<?= $val ?>" <?= checked_chip('subjects', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['subjects'])): ?><div class="field-error" style="display:block"><?= $errors['subjects'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label">What kind of activities excite you?</div>
          <div class="q-help">Pick as many as apply.</div>
          <div class="chips">
            <?php
            $actOptions = [
              'act_problems' => 'Solving problems / puzzles', 'act_create' => 'Creating things (art, design, writing)',
              'act_help_teach' => 'Helping or teaching people', 'act_lead' => 'Leading a team or business',
              'act_build_fix' => 'Building or fixing things', 'act_organise' => 'Organising and planning',
            ];
            foreach ($actOptions as $val => $label): ?>
              <label class="chip">
                <input type="checkbox" name="activities[]" value="<?= $val ?>" <?= checked_chip('activities', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['activities'])): ?><div class="field-error" style="display:block"><?= $errors['activities'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label">What working environment do you imagine for yourself?</div>
          <div class="chips">
            <?php
            $envOptions = [
              'env_office' => 'Office / desk-based', 'env_workshop' => 'Workshop or outdoors',
              'env_classroom' => 'Classroom or community', 'env_studio' => 'Studio / creative space',
              'env_onsite' => 'On-site, hands-on', 'env_mixed' => 'A mix of these',
            ];
            foreach ($envOptions as $val => $label): ?>
              <label class="chip">
                <input type="radio" name="environment" value="<?= $val ?>" <?= checked_radio('environment', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['environment'])): ?><div class="field-error" style="display:block"><?= $errors['environment'] ?></div><?php endif; ?>
        </div>
        <div class="wizard-nav">
          <button type="button" class="primary-btn next-step">Continue</button>
        </div>
      </section>

      <!-- STEP 2: Job fit -->
      <section class="step-panel" data-step="2">
        <div class="q-block">
          <div class="mini-label">How do you prefer to work?</div>
          <div class="chips">
            <?php
            $workOptions = [
              'work_independent' => 'Independently', 'work_team' => 'In a team',
              'work_lead' => 'Leading others', 'work_instructions' => 'Following clear instructions',
            ];
            foreach ($workOptions as $val => $label): ?>
              <label class="chip">
                <input type="radio" name="work_style" value="<?= $val ?>" <?= checked_radio('work_style', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['work_style'])): ?><div class="field-error" style="display:block"><?= $errors['work_style'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label">What matters most to you in a future job?</div>
          <div class="q-help">Pick as many as apply.</div>
          <div class="chips">
            <?php
            $valueOptions = [
              'value_stability' => 'Stability', 'value_earnings' => 'High earning potential',
              'value_creativity' => 'Creativity / freedom', 'value_helping' => 'Helping others',
              'value_recognition' => 'Recognition / leadership', 'value_learning' => 'Continuous learning',
            ];
            foreach ($valueOptions as $val => $label): ?>
              <label class="chip">
                <input type="checkbox" name="values[]" value="<?= $val ?>" <?= checked_chip('values', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['values'])): ?><div class="field-error" style="display:block"><?= $errors['values'] ?></div><?php endif; ?>
        </div>
        <div class="q-block">
          <div class="mini-label">How do you feel about further study?</div>
          <div class="chips">
            <?php
            $routeOptions = [
              'route_university' => 'University degree (3–4+ yrs)', 'route_tvet' => 'TVET / college diploma (1–3 yrs)',
              'route_learnership' => 'Learnership / apprenticeship', 'route_unsure' => 'Not sure yet',
            ];
            foreach ($routeOptions as $val => $label): ?>
              <label class="chip">
                <input type="radio" name="route" value="<?= $val ?>" <?= checked_radio('route', $val, $old) ?>>
                <span><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($errors['route'])): ?><div class="field-error" style="display:block"><?= $errors['route'] ?></div><?php endif; ?>
        </div>
        <div class="wizard-nav">
          <button type="button" class="ghost-btn prev-step">Back</button>
          <button type="submit" class="primary-btn">See my career matches</button>
        </div>
      </section>

    </form>
    <p class="progress-caption" id="progressCaption">Step 1 of 2</p>
  </main>
</div>

<script>
(function(){
  var panels = Array.prototype.slice.call(document.querySelectorAll('.step-panel'));
  var steps  = Array.prototype.slice.call(document.querySelectorAll('#journeyStrip .journey-step'));
  var caption = document.getElementById('progressCaption');
  var current = <?php
    if (empty($errors)) {
        echo 1;
    } elseif (!empty($errors['subjects']) || !empty($errors['activities']) || !empty($errors['environment'])) {
        echo 1;
    } else {
        echo 2;
    }
  ?>;

  function show(n){
    panels.forEach(function(p){ p.classList.toggle('active', +p.dataset.step === n); });
    steps.forEach(function(s){ s.classList.toggle('active', +s.dataset.step <= n); });
    caption.textContent = 'Step ' + n + ' of 2';
    current = n;
    window.scrollTo({top: document.querySelector('.wizard-panel').offsetTop - 20, behavior:'smooth'});
  }

  document.querySelectorAll('.next-step').forEach(function(btn){
    btn.addEventListener('click', function(){
      var panel = btn.closest('.step-panel');
      var required = panel.querySelectorAll('[required]');
      show(Math.min(current + 1, 2));
    });
  });
  document.querySelectorAll('.prev-step').forEach(function(btn){
    btn.addEventListener('click', function(){ show(Math.max(current - 1, 1)); });
  });

  show(current);
})();
</script>
</body>
</html>