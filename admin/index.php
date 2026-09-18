<?php
/**
 * Khetha Path — admin dashboard: where the skills gaps are.
 *
 * Access: a single admin password, KHETHA_ADMIN_PASSWORD. If it isn't set the page stays
 * locked (fails closed). Data: kp_db(), so KHETHA_USE_DB=1 must be set too. Either variable
 * can live in the real environment or in database/.env (gitignored) — only KHETHA_* keys
 * are read from that file here.
 */
session_start();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-insights.php';

$envFile = __DIR__ . '/../database/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, 'KHETHA_') && str_contains($line, '=') && getenv(explode('=', $line, 2)[0]) === false) putenv(trim($line));
    }
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$adminPassword = (string)getenv('KHETHA_ADMIN_PASSWORD');
$error = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['kp_admin']);
    header('Location: index.php');
    exit;
}
if ($adminPassword !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check($_POST['csrf'] ?? '') && hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['kp_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect password.';
}
$authed = $adminPassword !== '' && !empty($_SESSION['kp_admin']);

$db = $authed ? kp_db() : null;
$data = $db ? admin_insights($db) : null;

/** A horizontal bar: $value out of $max, as a share of the track. */
$bar = fn(int $value, int $max, string $cls = '') => '<span class="ad-track"><span class="ad-fill ' . $cls . '" style="width:' . ($max > 0 ? round($value / $max * 100) : 0) . '%"></span></span>';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Skills gap insights — Khetha Path</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.ad-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:26px 0}
.ad-grid .panel h2{font-size:34px;margin:4px 0 2px}
.ad-two{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}
.ad-row{display:grid;grid-template-columns:minmax(120px,1.1fr) 2fr auto;gap:12px;align-items:center;padding:9px 0;border-top:1px solid var(--line);font-size:13px}
.ad-row:first-of-type{border-top:0}
.ad-row small{display:block;color:var(--muted);font-size:11px}
.ad-row b{font-variant-numeric:tabular-nums}
.ad-track{display:block;height:10px;background:var(--mint);border-radius:99px;overflow:hidden}
.ad-fill{display:block;height:100%;background:var(--teal);border-radius:99px}
.ad-fill.gap{background:#d9534f}.ad-fill.warn{background:#e0a100}
.ad-tag{font-size:10px;font-weight:800;letter-spacing:.5px;padding:3px 8px;border-radius:99px;background:var(--mint);color:#087c73}
.ad-tag.high{background:#fdecea;color:#b3312d}
.ad-note{background:#fff7df;border-radius:14px;padding:14px 16px;font-size:13px;line-height:1.55;margin-top:20px}
.ad-login{max-width:380px;margin:80px auto;background:#fff;border:1px solid var(--line);border-radius:20px;padding:26px}
.ad-login label{display:block;font-size:12px;font-weight:800;margin-top:14px}
.ad-login input{margin-top:6px}
@media(max-width:900px){.ad-grid{grid-template-columns:1fr 1fr}.ad-two{grid-template-columns:1fr}}
@media(max-width:600px){.ad-row{grid-template-columns:1fr auto}.ad-row .ad-track{grid-column:1/-1;order:3}}
</style>
</head>
<body>
<div class="app-shell">
<header class="topbar">
  <a class="brand" href="../index.php"><span class="brand-mark">K</span><span>Khetha <b>Path</b></span></a>
  <span>
    <span class="status-pill">Admin</span>
    <?php if ($authed): ?><a class="ghost-btn" style="margin-left:8px" href="?logout=1">Sign out</a><?php endif; ?>
  </span>
</header>

<?php if (!$authed): ?>
  <main class="ad-login">
    <p class="eyebrow">ADMIN</p>
    <h1 style="font-size:26px;margin:0 0 6px">Skills gap insights</h1>
    <?php if ($adminPassword === ''): ?>
      <p class="muted">Admin access isn't set up. Set <b>KHETHA_ADMIN_PASSWORD</b> in the environment or in <b>database/.env</b>.</p>
    <?php else: ?>
      <?php if ($error): ?><div class="error-box"><?= $h($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= $h(csrf_token()) ?>">
        <label>Admin password<input type="password" name="password" autocomplete="current-password" required autofocus></label>
        <button class="primary-btn" type="submit">Sign in</button>
      </form>
    <?php endif; ?>
  </main>

<?php elseif (!$data): ?>
  <main class="dashboard narrow">
    <h1>Skills gap insights</h1>
    <div class="ad-note">Can't reach the database. Set <b>KHETHA_USE_DB=1</b> (environment or <b>database/.env</b>) and check the connection settings.</div>
  </main>

<?php else: ?>
  <main class="dashboard">
    <p class="eyebrow">ADMIN · SKILLS GAP INSIGHTS</p>
    <h1 style="font-size:clamp(32px,4vw,44px);letter-spacing:-1px">Where the gaps are</h1>
    <p class="lead">Live from the database: where learners' subjects and marks fall short of what their chosen careers need, and which in-demand careers few learners are heading towards.</p>

    <div class="ad-grid">
      <section class="panel"><p class="eyebrow">LEARNERS</p><h2><?= $data['learners'] ?></h2><p class="muted">registered</p></section>
      <section class="panel"><p class="eyebrow">CHOSE SUBJECTS</p><h2><?= $data['with_chooser'] ?></h2><p class="muted">used Subject Chooser</p></section>
      <section class="panel"><p class="eyebrow">CAREER CHOICE</p><h2><?= $data['with_choice'] ?></h2><p class="muted">completed the quiz</p></section>
      <section class="panel"><p class="eyebrow">AVG JOB FIT</p><h2><?= $data['avg_fit'] === null ? '—' : $data['avg_fit'] . '%' ?></h2><p class="muted"><?= $data['job_fit_count'] ?> Job Fit checks</p></section>
    </div>

    <?php if ($data['learners'] === 0 && $data['with_chooser'] === 0): ?>
      <div class="ad-note">No learner data yet. Once learners register and use the tools with database mode on, this page fills in. To preview it with sample data, run <b>database/seed_demo_insights.sql</b>.</div>
    <?php endif; ?>

    <div class="ad-two">
      <section class="panel">
        <p class="eyebrow">SUBJECT GAPS</p>
        <h2 style="font-size:19px;margin:0 0 4px">Required subjects learners are short of</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Learners aiming for a career that requires the subject, who aren't taking it or are marked below its minimum.</p>
        <?php foreach (array_slice($data['subject_gaps'], 0, 8) as $g): ?>
          <div class="ad-row">
            <div><?= $h($g['subject']) ?><small><?= $g['not_taking'] ?> not taking · <?= $g['low_mark'] ?> below minimum mark</small></div>
            <?= $bar($g['short'], $g['needed'], 'gap') ?>
            <b><?= $g['short'] ?> of <?= $g['needed'] ?></b>
          </div>
        <?php endforeach; ?>
        <?php if (!$data['subject_gaps']): ?><p class="muted">No subject gaps found.</p><?php endif; ?>
      </section>

      <section class="panel">
        <p class="eyebrow">TALENT PIPELINE</p>
        <h2 style="font-size:19px;margin:0 0 4px">In-demand careers with few learners heading there</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Learners who picked the career in Subject Chooser or had it in their Career Choice top matches.</p>
        <?php $maxL = max(1, ...array_column($data['pipeline'], 'learners')); ?>
        <?php foreach (array_slice($data['pipeline'], 0, 8) as $c): ?>
          <div class="ad-row">
            <div><?= $h($c['title']) ?><small><?= $h($c['field']) ?></small></div>
            <?= $bar($c['learners'], $maxL, $c['learners'] === 0 ? 'gap' : 'warn') ?>
            <span><span class="ad-tag <?= $c['demand'] === 'high' ? 'high' : '' ?>"><?= strtoupper($h($c['demand'])) ?></span> <b><?= $c['learners'] ?></b></span>
          </div>
        <?php endforeach; ?>
      </section>
    </div>

    <div class="ad-two">
      <section class="panel">
        <p class="eyebrow">JOB FIT</p>
        <h2 style="font-size:19px;margin:0 0 12px">Most common reasons a career doesn't fit</h2>
        <?php $maxF = max(1, ...array_values($data['top_flags'] ?: [0])); ?>
        <?php foreach ($data['top_flags'] as $text => $n): ?>
          <div class="ad-row" style="grid-template-columns:2fr 1fr auto">
            <div><?= $h($text) ?></div><?= $bar($n, $maxF, 'warn') ?><b><?= $n ?></b>
          </div>
        <?php endforeach; ?>
        <?php if (!$data['top_flags']): ?><p class="muted">No Job Fit warnings recorded.</p><?php endif; ?>
      </section>

      <section class="panel">
        <p class="eyebrow">WHO'S USING IT</p>
        <h2 style="font-size:19px;margin:0 0 12px">Learners by grade</h2>
        <?php $maxG = max(1, ...array_values($data['grades'] ?: [0])); ?>
        <?php foreach ($data['grades'] as $grade => $n): ?>
          <div class="ad-row"><div><?= $h($grade) ?></div><?= $bar($n, $maxG) ?><b><?= $n ?></b></div>
        <?php endforeach; ?>
        <?php if (!$data['grades']): ?><p class="muted">No learners yet.</p><?php endif; ?>
      </section>
    </div>
  </main>
<?php endif; ?>
</div>
</body>
</html>
