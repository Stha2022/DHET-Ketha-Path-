<?php
// Demo mode: no database, so there's no real account to check against.
// Any well-formed email + non-empty password signs in with a fresh demo
// profile (name guessed from the email) — nothing is persisted.
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        session_regenerate_id(true);

        $guessedName = ucwords(str_replace(['.', '_', '+'], ' ', explode('@', $email)[0]));

        $_SESSION['user'] = [
            'id' => 1,
            'name' => $guessedName,
            'email' => $email,
            'grade' => 'Grade 11',
            'subjects' => [],
            'interest' => ''
        ];
        $_SESSION['name'] = $guessedName;
        $_SESSION['subjects'] = [];
        $_SESSION['grade'] = 'Grade 11';
        $_SESSION['interest'] = '';

        header('Location: dashboard.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in — Khetha</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
<header class="topbar">
  <a class="brand" href="index.php"><img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha"></a>
  <a class="ghost-btn" href="register.php">Create account</a>
</header>

<main class="auth-layout">
<section class="auth-intro">
  <p class="eyebrow">WELCOME BACK</p>
  <h1>Your journey is waiting.</h1>
  <p class="lead">Sign in to continue with your saved profile, assessments and pathway.</p>
</section>

<section class="auth-card">
  <div class="step-label">→ &nbsp; SIGN IN</div>

  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Email
      <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button class="primary-btn" type="submit">Continue My Journey →</button>
  </form>

  <div class="demo-note">🧪 Demo mode — no database. Any email and password will sign you in with a fresh session; nothing is checked or stored. Prefer a personalised profile? <a href="register.php">Create one</a> instead.</div>
  <p class="auth-switch">New to Khetha? <a href="register.php">Create your profile</a></p>
</section>
</main>
</body>
</html>