<?php
session_start();
require_once __DIR__ . '/subject-choice-data.php';

$type = $_GET['type'] ?? $_POST['type'] ?? '';
if (!sc_is_valid_type($type)) {
    header('Location: subject-choice.php');
    exit;
}

$types      = sc_questionnaire_types();
$meta       = $types[$type];
$statements = sc_statements($type);
$errors     = [];
$old        = $_SESSION['subject_choice']['answers'][$type] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $_POST['answers'] ?? [];
    $answers = [];
    foreach ($statements as $i => $s) {
        $val = $posted[$i] ?? '';
        if ($val === '' || !in_array((int)$val, [1, 2, 3, 4, 5], true)) {
            $errors[$i] = 'Please rate this statement.';
        } else {
            $answers[$i] = (int)$val;
        }
    }
    $old = $posted;

    if (empty($errors)) {
        $_SESSION['subject_choice']['answers'][$type] = $answers;
        $_SESSION['subject_choice']['done'][$type]     = true;

        header('Location: questionnaire-report.php?type=' . urlencode($type));
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($meta['label']) ?> — Khetha Path</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/assets/navbar.php'; ?>

  <main class="dashboard narrow">
    <div class="welcome-row">
      <div>
        <h1><?= htmlspecialchars($meta['label']) ?></h1>
        <p class="muted"><?= htmlspecialchars($meta['desc']) ?> Rate each statement from Strongly disagree to Strongly agree — there are no right or wrong answers.</p>
      </div>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="field-error" style="display:block;font-size:13px;margin-bottom:18px">Please rate every statement before continuing — <?= count($errors) ?> left below.</div>
    <?php endif; ?>

    <form class="wizard-panel" method="post" action="questionnaire.php?type=<?= urlencode($type) ?>" style="max-width:720px">
      <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
      <?php foreach ($statements as $i => $s): ?>
        <div class="lk-block <?= isset($errors[$i]) ? 'error' : '' ?>">
          <p><?= $i + 1 ?>. <?= htmlspecialchars($s['text']) ?></p>
          <div class="lk-scale">
            <?php foreach ([1, 2, 3, 4, 5] as $v): ?>
              <label class="lk-opt">
                <input type="radio" name="answers[<?= $i ?>]" value="<?= $v ?>" <?= (isset($old[$i]) && (int)$old[$i] === $v) ? 'checked' : '' ?>>
                <span><?= $v ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="lk-caption"><span>Strongly disagree</span><span>Strongly agree</span></div>
          <?php if (isset($errors[$i])): ?><div class="field-error"><?= $errors[$i] ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div class="wizard-nav">
        <a class="ghost-btn" href="subject-choice.php">Back</a>
        <button type="submit" class="primary-btn">See my results</button>
      </div>
    </form>
  </main>
</div>

<script>
document.querySelectorAll('.lk-opt input').forEach(function(input){
  input.addEventListener('change', function(){
    var scale = input.closest('.lk-scale');
    scale.querySelectorAll('.lk-opt').forEach(function(opt){ opt.classList.remove('selected'); });
    input.closest('.lk-opt').classList.add('selected');
  });
});
</script>
</body>
</html>
