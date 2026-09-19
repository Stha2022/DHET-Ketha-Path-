<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assessment-relevance-data.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/journey.php';
kp_require_auth();

$profile = profile_get();
$completion = profile_completion();
$first = explode(' ', $profile['name'] !== '' ? $profile['name'] : $_SESSION['user']['name'])[0];
$grade = $profile['grade'] !== '' ? $profile['grade'] : AR_FALLBACK_GRADE;
$subjectCount = count($profile['subjects']) + ($profile['maths_track'] !== '' ? 1 : 0);
$savedCount = count(kp_favourites(kp_user_id()));
kp_seed_journey_reminders(kp_user_id());
$unreadNotifications = kp_unread_notification_count(kp_user_id());
$next = $completion['next'] ?? ['label' => t('My Path'), 'url' => 'my-path.php'];
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><?php include __DIR__ . '/assets/pwa-head.php'; ?>
<title><?= t('My Khetha Dashboard') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/assets/navbar.php'; ?>
<main id="main-content" class="dashboard dashboard-lite kp-home">
  <section class="kp-hero">
    <div class="kp-hero-copy">
      <div class="kp-kicker"><span class="spark">✦</span> MY KHETHA</div>
      <h1>Hi, <?= htmlspecialchars($first) ?>! <span>👋</span></h1>
      <p>Let's find your next move.</p>
      <a class="primary-btn kp-hero-btn" href="<?= htmlspecialchars($next['url']) ?>">Keep going <span>→</span></a>
      <div class="kp-hero-bubbles" aria-hidden="true"><i>🎓</i><i>💻</i><i>🚀</i></div>
    </div>
    <div class="kp-avatar-stage">
      <div class="kp-glow"></div>
      <video class="greeting-video" autoplay muted loop playsinline preload="auto" poster="assets/images/khetha-logo.png" aria-label="Khetha greeting">
        <source src="assets/media/khetha-greeting.mp4" type="video/mp4">
      </video>
      <div class="kp-speech">Hi! I'm Khetha 👋</div>
    </div>
  </section>

  <section class="kp-notice-bar" aria-label="Notifications"><a href="notifications.php" class="kp-notice-link"><span>🔔</span><b>Notifications</b><?php if ($unreadNotifications > 0): ?><strong><?= (int)$unreadNotifications ?></strong><?php endif; ?><small><?= $unreadNotifications ? "You have new journey reminders." : "You are up to date." ?></small></a></section>

  <section class="kp-actions" aria-label="Khetha quick actions">
    <a class="kp-action kp-blue" href="subject.php"><span>📚</span><b>Subjects</b><small>Choose</small></a>
    <a class="kp-action kp-green" href="career-quiz.php"><span>🧭</span><b>Careers</b><small>Discover</small></a>
    <a class="kp-action kp-purple" href="my-path.php"><span>🛣️</span><b>My Path</b><small>Continue</small></a>
    <a class="kp-action kp-yellow" href="ask.php"><span>✨</span><b>Ask Khetha</b><small>Get help</small></a>
  </section>

  <section class="kp-progress-card">
    <div class="kp-progress-copy"><span>YOUR JOURNEY</span><b><?= (int)$completion['percent'] ?>%</b><small><?= $subjectCount ? htmlspecialchars($subjectCount . ' subject' . ($subjectCount === 1 ? '' : 's') . ' saved') : 'Start exploring' ?></small></div>
    <div class="kp-progress-track"><i style="width:<?= (int)$completion['percent'] ?>%"></i></div>
    <a href="my-path.php">My Path →</a>
  </section>

  <section class="kp-mini-grid">
    <div><span>LEVEL</span><b><?= htmlspecialchars($grade) ?></b></div>
    <div><span>SAVED</span><b><?= (int)$savedCount ?> favourites</b></div>
    <div><span>NEXT</span><b><?= htmlspecialchars($next['label']) ?></b></div>
  </section>

  <section class="kp-discover">
    <div><span class="kp-discover-icon">🚀</span><div><b>Big dreams. Small steps.</b><small>Khetha keeps your journey together.</small></div></div>
    <a href="directories.php">Explore →</a>
  </section>

  <div class="kp-ncap-strip"><span>🏛️ <b>NCAP-aligned</b></span><small>Subject Chooser · Career Choice · Job Fit · Careers · What to Study · Where to Study</small><a href="ncap.php">See how Khetha maps to NCAP →</a></div>

  <p class="offline-hint">📥 Works with low data. Key Khetha content can stay available offline.</p>
</main>
</body>
</html>
