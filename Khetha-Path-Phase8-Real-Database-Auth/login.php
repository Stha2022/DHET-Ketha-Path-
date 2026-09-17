<?php
session_start();
require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, name, email, password_hash, grade
                 FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'The email or password is incorrect.';
            } else {
                $a = $pdo->prepare(
                    'SELECT subjects, interests
                     FROM assessments
                     WHERE user_id = ?
                     ORDER BY id DESC LIMIT 1'
                );
                $a->execute([$user['id']]);
                $assessment = $a->fetch();

                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'grade' => $user['grade'],
                    'subjects' => $assessment && $assessment['subjects'] ? (json_decode($assessment['subjects'], true) ?: []) : [],
                    'interest' => $assessment['interests'] ?? ''
                ];

                $_SESSION['name'] = $user['name'];
                $_SESSION['subjects'] = $_SESSION['user']['subjects'];
                $_SESSION['grade'] = $user['grade'];
                $_SESSION['interest'] = $_SESSION['user']['interest'];

                $journey = $pdo->prepare(
                    'INSERT INTO journey_events (user_id, event_type, event_data)
                     VALUES (?, ?, ?)'
                );
                $journey->execute([
                    $user['id'],
                    'login',
                    json_encode(['source' => 'web'], JSON_UNESCAPED_UNICODE)
                ]);

                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Khetha login failed: ' . $e->getMessage());
            $error = 'We could not connect to your account right now. Please check that MySQL is running.';
        }
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

  <div class="demo-note">Your credentials are checked against the Khetha MySQL database. Passwords are never stored as plain text.</div>
  <p class="auth-switch">New to Khetha? <a href="register.php">Create your profile</a></p>
</section>
</main>
</body>
</html>