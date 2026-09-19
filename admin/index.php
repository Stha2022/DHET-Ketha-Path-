<?php
/**
 * Khetha Path — admin dashboard: where the skills gaps are.
 *
 * Access: a single admin password, KHETHA_ADMIN_PASSWORD. If it isn't set the page stays
 * locked (fails closed). The key can live in the real environment or in database/.env
 * (gitignored) — db-connection.php, included via insights.php, loads that file for us.
 *
 * Rendering only. Every number comes from admin_insights() in insights.php (same folder).
 */
session_start();
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/insights.php';

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

$grade = (string)($_GET['grade'] ?? '');
$db    = $authed ? kp_db() : null;
$data  = $db ? admin_insights($db, $grade) : null;
$grade = $data['grade'] ?? '';

/** A horizontal bar: $value out of $max, as a share of the track. */
$bar = fn(int $value, int $max, string $cls = '') => '<span class="ad-track"><span class="ad-fill ' . $cls . '" style="width:' . ($max > 0 ? round($value / $max * 100) : 0) . '%"></span></span>';
/** Largest value in a list, never below 1, so a bar never divides by zero. */
$peak = fn(array $values) => max(1, ...($values ?: [0]));
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
/* Same lockup and sticky bar as assets/navbar.php, without the learner-only
   controls (profile, notifications, language) that don't apply to an admin.
   The rules are repeated here because navbar.php's own paths are relative to
   the project root and would break one folder down. */
.kp-navbar{min-height:72px;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--line,#dbe7ea);padding:0 4%;position:sticky;top:0;z-index:1000;display:flex;align-items:center;justify-content:space-between}
.kp-brand-lockup{display:flex;align-items:center;gap:13px;color:var(--ink,#172b4d)}
.kp-navbar .khetha-logo{width:124px}
.kp-gov-divider{width:1px;height:34px;background:#dbe7ea}
.kp-gov-logo{width:112px;height:42px;object-fit:contain;object-position:left center}
.kp-navbar .ad-right{display:flex;align-items:center;gap:10px}
@media(max-width:600px){.kp-navbar{min-height:64px;padding:0 12px}.kp-navbar .khetha-logo{width:100px}.kp-gov-divider{height:28px}.kp-gov-logo{width:82px;height:34px}}
@media(max-width:420px){.kp-gov-divider,.kp-gov-logo{display:none}}
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
.ad-tag.medium{background:#fff3d6;color:#8a6300}
.ad-note{background:#fff7df;border-radius:14px;padding:14px 16px;font-size:13px;line-height:1.55;margin-top:20px}
.ad-login{max-width:380px;margin:80px auto;background:#fff;border:1px solid var(--line);border-radius:20px;padding:26px}
.ad-login label{display:block;font-size:12px;font-weight:800;margin-top:14px}
.ad-login input{margin-top:6px}
.ad-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:18px 0 0}
.ad-filter a{font-size:12px;font-weight:700;text-decoration:none;padding:6px 13px;border-radius:99px;border:1px solid var(--line);color:inherit;background:#fff}
.ad-filter a.on{background:var(--teal);border-color:var(--teal);color:#fff}
.ad-filter span{font-size:11px;color:var(--muted);font-weight:700;letter-spacing:.5px}
.ad-warn{border-top:1px solid var(--line);padding:12px 0}
.ad-warn:first-of-type{border-top:0}
.ad-warn summary{display:flex;gap:10px;align-items:center;cursor:pointer;font-size:13px;font-weight:700;list-style:none}
.ad-warn summary::-webkit-details-marker{display:none}
.ad-warn summary b{margin-left:auto;font-variant-numeric:tabular-nums;font-size:16px}
.ad-warn p{margin:8px 0 0;font-size:12px;color:var(--muted);line-height:1.5}
.ad-people{margin:10px 0 0;padding:0;list-style:none;max-height:230px;overflow:auto}
.ad-people li{font-size:12px;padding:6px 0;border-top:1px dashed var(--line);display:grid;grid-template-columns:1fr auto;gap:4px 10px}
.ad-people small{grid-column:1/-1;color:var(--muted)}
.ad-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.ad-chip{font-size:11px;padding:4px 10px;border-radius:99px;background:var(--mint);color:#087c73;font-weight:700}
.ad-group{padding:10px 0;border-top:1px solid var(--line)}
.ad-group:first-of-type{border-top:0}
.ad-group h3{font-size:13px;margin:0}
.ad-group h3 span{float:right;color:var(--muted);font-weight:600;font-size:12px}
@media(max-width:900px){.ad-grid{grid-template-columns:1fr 1fr}.ad-two{grid-template-columns:1fr}}
@media(max-width:600px){.ad-row{grid-template-columns:1fr auto}.ad-row .ad-track{grid-column:1/-1;order:3}}
</style>
</head>
<body>
<div class="app-shell">
<a class="skip-link" href="#main-content">Skip to main content</a>
<nav class="kp-navbar">
  <a class="kp-brand-lockup" href="../index.php" aria-label="Khetha">
    <img class="khetha-logo small-logo" src="../assets/images/khetha-logo.png" alt="Khetha">
    <span class="kp-gov-divider" aria-hidden="true"></span>
    <img class="kp-gov-logo" src="../assets/images/dhet-official-logo.png" alt="Department of Higher Education and Training">
  </a>
  <div class="ad-right">
    <span class="status-pill">Admin</span>
    <?php if ($authed): ?><a class="ghost-btn" href="?logout=1">Sign out</a><?php endif; ?>
  </div>
</nav>

<?php if (!$authed): ?>
  <main class="ad-login" id="main-content">
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
  <main class="dashboard narrow" id="main-content">
    <h1>Skills gap insights</h1>
    <div class="ad-note">Can't reach the database. Check the connection settings in <b>database/.env</b> (or the <b>KHETHA_DB_*</b> environment variables on the host).</div>
  </main>

<?php else: ?>
  <main class="dashboard" id="main-content">
    <p class="eyebrow">ADMIN · SKILLS GAP INSIGHTS</p>
    <h1 style="font-size:clamp(32px,4vw,44px);letter-spacing:-1px">Where the gaps are</h1>
    <p class="lead">Live from the database: which learners need someone to step in, where their subjects and marks fall short of what their chosen careers need, and which in-demand careers few learners are heading towards.</p>

    <nav class="ad-filter">
      <span>GRADE</span>
      <a href="?" class="<?= $grade === '' ? 'on' : '' ?>">All</a>
      <?php foreach (ADMIN_GRADE_LABELS as $key => $label): ?>
        <a href="?grade=<?= urlencode($key) ?>" class="<?= $grade === $key ? 'on' : '' ?>"><?= $h($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="ad-grid">
      <section class="panel"><p class="eyebrow">LEARNERS</p><h2><?= $data['learners'] ?></h2><p class="muted"><?= $grade === '' ? 'registered' : 'in ' . $h(ADMIN_GRADE_LABELS[$grade]) ?></p></section>
      <section class="panel"><p class="eyebrow">NEW</p><h2><?= $data['new_30'] ?></h2><p class="muted">joined in the last 30 days</p></section>
      <section class="panel"><p class="eyebrow">NEEDS ATTENTION</p><h2 style="<?= $data['at_risk'] ? 'color:#b3312d' : '' ?>"><?= $data['at_risk'] ?></h2><p class="muted">learners with an urgent flag</p></section>
      <section class="panel"><p class="eyebrow">AVG JOB FIT</p><h2><?= $data['avg_fit'] === null ? '—' : $data['avg_fit'] . '%' ?></h2><p class="muted"><?= $data['job_fit_count'] ?> Job Fit checks</p></section>
    </div>

    <?php if ($data['learners'] === 0): ?>
      <div class="ad-note"><?= $grade === '' ? 'No learner data yet. Once learners register and use the tools, this page fills in. To preview it with sample data, run <b>admin/seed_demo_insights.sql</b>.' : 'No learners in ' . $h(ADMIN_GRADE_LABELS[$grade]) . ' yet.' ?></div>
    <?php endif; ?>

    <div class="ad-two">
      <section class="panel">
        <p class="eyebrow">WHO NEEDS SOMEONE TO STEP IN</p>
        <h2 style="font-size:19px;margin:0 0 4px">Early warnings</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Most urgent first. Open a group to see the learners in it and what to do about it.</p>
        <?php $anyWarning = false; ?>
        <?php foreach ($data['warnings'] as $w): ?>
          <?php if (!$w['learners']) continue; $anyWarning = true; ?>
          <details class="ad-warn">
            <summary>
              <span class="ad-tag <?= $h($w['level']) ?>"><?= strtoupper($h($w['level'])) ?></span>
              <span><?= $h($w['label']) ?></span>
              <b><?= count($w['learners']) ?></b>
            </summary>
            <p><?= $h($w['action']) ?></p>
            <ul class="ad-people">
              <?php foreach ($w['learners'] as $l): ?>
                <li>
                  <span><b><?= $h($l['name'] ?: $l['email']) ?></b> · <?= $h($l['email']) ?></span>
                  <span class="ad-tag"><?= $h($l['grade']) ?></span>
                  <small><?= $h($l['detail']) ?></small>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
        <?php if (!$anyWarning): ?><p class="muted">No warnings — no learner is currently missing a required subject or drifting.</p><?php endif; ?>
      </section>

      <section class="panel">
        <p class="eyebrow">HOW FAR THEY GET</p>
        <h2 style="font-size:19px;margin:0 0 4px">Funnel</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Learners who reached each tool. A drop between two steps is where they give up.</p>
        <?php $maxStep = $peak(array_values($data['funnel'])); ?>
        <?php foreach ($data['funnel'] as $step => $n): ?>
          <div class="ad-row">
            <div><?= $h($step) ?><small><?= $data['funnel']['Registered'] > 0 ? round($n / $data['funnel']['Registered'] * 100) . '% of registered' : '—' ?></small></div>
            <?= $bar($n, $maxStep) ?>
            <b><?= $n ?></b>
          </div>
        <?php endforeach; ?>

        <p class="eyebrow" style="margin-top:20px">LEARNERS BY GRADE</p>
        <p class="muted" style="font-size:12px;margin:0 0 6px">Across every grade, so you can see what the filter above narrows to.</p>
        <?php $maxG = $peak(array_values($data['grades'])); ?>
        <?php foreach ($data['grades'] as $label => $n): ?>
          <div class="ad-row"><div><?= $h($label) ?></div><?= $bar($n, $maxG) ?><b><?= $n ?></b></div>
        <?php endforeach; ?>
        <?php if (!$data['grades']): ?><p class="muted">No learners yet.</p><?php endif; ?>
      </section>
    </div>

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
        <?php $maxL = $peak(array_column($data['pipeline'], 'learners')); ?>
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
        <p class="eyebrow">CAREER FIELDS</p>
        <h2 style="font-size:19px;margin:0 0 4px">What learners are drawn to</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Learners interested in each field, and how many of them are missing something the field requires.</p>
        <?php $maxFld = $peak(array_column($data['fields'], 'learners')); ?>
        <?php foreach (array_slice($data['fields'], 0, 8) as $f): ?>
          <div class="ad-row">
            <div><?= $h($f['field']) ?><small><?= $f['with_gap'] ?> with a subject gap</small></div>
            <?= $bar($f['learners'], $maxFld, $f['with_gap'] > $f['learners'] / 2 ? 'warn' : '') ?>
            <b><?= $f['learners'] ?></b>
          </div>
        <?php endforeach; ?>
        <?php if (!$data['fields']): ?><p class="muted">No career choices recorded yet.</p><?php endif; ?>
      </section>

      <section class="panel">
        <p class="eyebrow">JOB FIT</p>
        <h2 style="font-size:19px;margin:0 0 12px">Most common reasons a career doesn't fit</h2>
        <?php $maxF = $peak(array_values($data['top_flags'])); ?>
        <?php foreach ($data['top_flags'] as $text => $n): ?>
          <div class="ad-row" style="grid-template-columns:2fr 1fr auto">
            <div><?= $h($text) ?></div><?= $bar($n, $maxF, 'warn') ?><b><?= $n ?></b>
          </div>
        <?php endforeach; ?>
        <?php if (!$data['top_flags']): ?><p class="muted">No Job Fit warnings recorded.</p><?php endif; ?>
      </section>
    </div>

    <div class="ad-two">
      <section class="panel">
        <p class="eyebrow">PERSONALITY</p>
        <h2 style="font-size:19px;margin:0 0 4px">Career Choice types</h2>
        <p class="muted" style="font-size:12px;margin:0 0 12px">Each learner's strongest type from their latest Career Choice result.</p>
        <?php $maxP = $peak(array_column($data['personality'], 'learners')); ?>
        <?php foreach ($data['personality'] as $p): ?>
          <div class="ad-row">
            <div><?= $h($p['label']) ?><small>Type <?= $h($p['letter']) ?></small></div>
            <?= $bar($p['learners'], $maxP) ?>
            <b><?= $p['learners'] ?></b>
          </div>
        <?php endforeach; ?>
      </section>

      <section class="panel">
        <p class="eyebrow">INTERESTS</p>
        <h2 style="font-size:19px;margin:0 0 12px">What they picked on their profile</h2>
        <?php if ($data['chip_groups'] === null): ?>
          <p class="muted">Interest chips aren't stored yet. Apply <b>database/migration_cv_edits.sql</b> to add the <b>users.interests</b> column.</p>
        <?php elseif (!array_filter(array_column($data['chip_groups'], 'learners'))): ?>
          <p class="muted">No learner has picked interests yet.</p>
        <?php else: ?>
          <?php foreach ($data['chip_groups'] as $group => $g): ?>
            <?php if (!$g['learners']) continue; ?>
            <div class="ad-group">
              <h3><?= $h($group) ?><span><?= $g['learners'] ?> learner<?= $g['learners'] === 1 ? '' : 's' ?></span></h3>
              <div class="ad-chips">
                <?php foreach (array_slice($g['chips'], 0, 6, true) as $chip => $n): ?>
                  <span class="ad-chip"><?= $h($chip) ?> <b><?= $n ?></b></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    </div>
  </main>
<?php endif; ?>
</div>
</body>
</html>
