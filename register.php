<?php
// Demo mode: no database. The profile lives only in this browser session —
// nothing is persisted, so there's nothing to check for a duplicate email.
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $grade    = trim($_POST['grade'] ?? '');
    $subjects = $_POST['subjects'] ?? [];
    $interest = trim($_POST['interest'] ?? '');
    $consent  = isset($_POST['consent']);

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$grade || !is_array($subjects) || count($subjects) === 0 || !$consent) {
        $error = 'Please complete all required fields, select at least one subject and accept the consent statement.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => 1,
            'name' => $name,
            'email' => $email,
            'grade' => $grade,
            'subjects' => array_values($subjects),
            'interest' => $interest
        ];
        $_SESSION['name'] = $name;
        $_SESSION['subjects'] = array_values($subjects);
        $_SESSION['grade'] = $grade;
        $_SESSION['interest'] = $interest;

        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title>Create your Khetha profile</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<header class="topbar">
  <a class="brand" href="index.php">
    <img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha">
  </a>
  <span class="status-pill">Your journey starts here</span>
</header>

<main class="auth-layout">
<section class="auth-intro">
  <p class="eyebrow">CREATE YOUR KHETHA PROFILE</p>
  <h1>Let’s make this personal.</h1>
  <p class="lead">Your answers become the starting point for a personalised career journey.</p>
  <div class="privacy-card">
    <b>🧪 Demo mode — no database.</b>
    <p>This deployment doesn't use a database. Your profile is kept only in this browser session for the demo, and disappears when you sign out or the session expires.</p>
  </div>
</section>

<section class="auth-card">
  <div class="auth-progress"><span class="active">1</span><i></i><span>2</span><i></i><span>3</span></div>
  <div class="step-label">CREATE ACCOUNT &nbsp; • &nbsp; PERSONALISE</div>

  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Full name
      <input name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g. Lindiwe Mokoena">
    </label>

    <label>Email
      <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
    </label>

    <label>Password
      <input type="password" name="password" minlength="8" required placeholder="At least 8 characters">
    </label>

    <label>Current level
      <select name="grade" required>
        <option value="">Choose...</option>
        <?php foreach (['Grade 9','Grade 10','Grade 11','Grade 12','Post-school'] as $g): ?>
          <option <?= (($_POST['grade'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Subjects <small>Select all that apply</small></label>
    <div class="subject-grid">
      <?php foreach (['Mathematics','Mathematical Literacy','Physical Sciences','Life Sciences','IT','Computer Applications Technology','Accounting','Business Studies','Economics','Geography'] as $x): ?>
        <label class="subject-choice">
          <input type="checkbox" name="subjects[]" value="<?= htmlspecialchars($x) ?>">
          <span><?= htmlspecialchars($x) ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <label>What are you interested in?
      <small>This helps Khetha personalise your starting point.</small>
      <textarea name="interest" rows="3" placeholder="e.g. technology, healthcare, business, design..."><?= htmlspecialchars($_POST['interest'] ?? '') ?></textarea>
    </label>

    <label class="consent-row">
      <input type="checkbox" name="consent" value="1" required>
      <span>I understand that my information will be used to personalise my Khetha journey.</span>
    </label>

    <button class="primary-btn" type="submit">Create My Khetha Profile →</button>
  </form>

  <p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
</section>
</main>
</body>
</html>