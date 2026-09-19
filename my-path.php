<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/subject-data.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/matching.php';

// Everything on this page comes from the shared learner profile.
kp_require_auth();
$profile = profile_get();
$name = $profile['name'] !== '' ? $profile['name'] : t('there');
$grade = $profile['grade'];
$subjects = $profile['subjects'];
$mathsTrack = $profile['maths_track'];
$interests = $profile['interests'];

$completion = profile_completion();

// Pathway career, in order of preference:
//   1. the first career the learner intends that is OPEN with their subjects,
//   2. otherwise the best match for their interests / Career Choice result,
//   3. otherwise nothing yet: ask them to finish Subject Chooser.
// OPEN/CLOSED needs a Maths track and at least one subject to judge against.
$occupations = sj_occupations();
$canJudge = $mathsTrack !== '' && !empty($subjects);
$statusOf = fn(string $id) => sj_open_status($subjects, $profile['marks'], $mathsTrack, $occupations[$id]);

$pathway = null;      // occupation record
$pathwayWhy = '';     // one line on why it was picked
if ($canJudge) {
    foreach ($profile['intended_careers'] as $id) {
        if (isset($occupations[$id]) && $statusOf($id)['open']) {
            $pathway = kp_occupation($id);
            $pathwayWhy = t('You picked this, and it is open with your subjects.');
            break;
        }
    }
}
if (!$pathway) {
    $ranked = rank_careers($profile);
    // A score of 0 means neither interests nor a Career Choice result exist, so
    // "top result" would just be the alphabet.
    if ($ranked && $ranked[0]['score'] > 0) {
        $pathway = kp_occupation($ranked[0]['id']);
        $pathwayWhy = $ranked[0]['reasons'] ? implode('. ', $ranked[0]['reasons']) : t('A good match for your interests.');
    }
}
$pathwayStatus = ($pathway && $canJudge) ? $statusOf($pathway['id']) : null;

// Real qualification and provider names for this career, where the directory has them.
$firstQual = $pathway ? (kp_qualifications_for_occupation($pathway['id'])[0] ?? null) : null;
$providerNames = $firstQual ? array_slice(array_column(kp_providers_for_qualification($firstQual['id']), 'name'), 0, 3) : [];

// Journey strip: lit up by what the learner has actually done.
$steps = [
    t('Know Me')  => $grade !== '' && !empty($subjects),
    t('Explore')  => $profile['career_quiz']['code'] !== '' || !empty($profile['intended_careers']),
    t('Prepare')  => !empty($profile['job_fit']),
    t('Apply')    => false,
];
$notSet = t('Not added yet');
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('My Path') ?> — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css">
<style>.kp-pill{display:inline-block;margin:2px 4px 2px 0;padding:3px 10px;border:1px solid var(--line);border-radius:999px;background:#fff;font-size:12px;font-style:normal;font-weight:600}</style>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/assets/navbar.php'; ?>

<main id="main-content" class="dashboard">
<section class="welcome-row">
  <div><p class="eyebrow"><?= t('MY CAREER JOURNEY') ?></p><h1><?= t('Hi, {name}!', ['name' => $name]) ?> 👋</h1>
  <?php if ($grade !== ''): ?>
  <p class="muted"><?= t('You’re in <b>{grade}</b>. Khetha is adapting your journey around your starting point.', ['grade' => html_entity_decode(t($grade))]) ?></p>
  <?php endif; ?></div>
  <a class="ghost-btn" href="ask.php"><?= t('Ask Khetha') ?> ✦</a>
</section>

<section class="journey-strip">
  <?php $n = 0; foreach ($steps as $label => $done): $n++; ?>
    <?php if ($n > 1): ?><div class="journey-line"></div><?php endif; ?>
    <div class="journey-step<?= $done ? ' active' : '' ?>"><b>0<?= $n ?></b><span><?= $label ?></span></div>
  <?php endforeach; ?>
</section>

<div class="three-col">
<section class="panel">
 <div class="panel-title"><span>✦</span><div><small><?= t('KHETHA’S READ') ?></small><h2><?= t('Your starting point') ?></h2></div></div>
 <div class="profile-box">
   <p><span><?= t('Grade') ?></span><?= $grade !== '' ? htmlspecialchars(html_entity_decode(t($grade))) : $notSet ?></p>
   <p><span><?= t('Subjects') ?></span><?= $subjects ? implode(' • ', array_map(fn($s) => htmlspecialchars(html_entity_decode(t($s))), $subjects)) : $notSet ?></p>
   <p><span><?= t('Maths') ?></span><?= $mathsTrack !== '' ? htmlspecialchars(html_entity_decode(t($mathsTrack))) : $notSet ?></p>
   <p><span><?= t('Interests') ?></span><?php if ($interests): foreach ($interests as $chip): ?><em class="kp-pill"><?= htmlspecialchars($chip) ?></em><?php endforeach; else: echo $notSet; endif; ?></p>
 </div>
 <div class="insight"><b><?= t('Why this matters') ?></b><p><?= t('Your starting point changes which routes you should investigate. Khetha keeps your context attached to the journey.') ?></p></div>
</section>

<section class="panel path-panel">
 <?php if ($pathway): ?>
 <div class="panel-title"><span>◎</span><div><small><?= t('ADAPTIVE PATHWAY') ?></small><h2><?= htmlspecialchars($pathway['title']) ?></h2></div></div>
 <p class="muted"><?= htmlspecialchars($pathwayWhy) ?></p>
 <?php if ($pathwayStatus): ?>
 <p class="muted"><b><?= $pathwayStatus['open'] ? t('OPEN') . ' — ' : '' ?></b><?= htmlspecialchars($pathwayStatus['reason']) ?></p>
 <?php endif; ?>
 <p class="muted"><?= t('A prototype pathway based on your current profile. In production, pathway data would be grounded in approved NCAP/DHET content.') ?></p>
 <div class="path-timeline">
  <div class="path-node<?= $canJudge ? ' done' : '' ?>"><b><?= t('NOW') ?></b><span><?= t('Review subject requirements') ?></span></div>
  <div class="path-node"><b><?= t('NEXT') ?></b><span><?= t('Explore qualifications') ?><?= $firstQual ? ': ' . htmlspecialchars($firstQual['title']) : '' ?></span></div>
  <div class="path-node"><b><?= t('THEN') ?></b><span><?= t('Compare learning providers') ?><?= $providerNames ? ': ' . htmlspecialchars(implode(', ', $providerNames)) : '' ?></span></div>
  <div class="path-node"><b><?= t('LATER') ?></b><span><?= t('Explore opportunities') ?></span></div>
 </div>
 <a class="primary-btn center" href="what-if.php"><?= t('Try “What If?” →') ?></a>
 <a class="ghost-btn center" style="margin-top:10px" href="occupation.php?id=<?= urlencode($pathway['id']) ?>"><?= t('See this career →') ?></a>
 <?php else: ?>
 <div class="panel-title"><span>◎</span><div><small><?= t('ADAPTIVE PATHWAY') ?></small><h2><?= t('Your pathway starts here') ?></h2></div></div>
 <p class="muted"><?= t('Tell Khetha your subjects, Maths track and the careers you are considering, and it will pick a pathway to follow.') ?></p>
 <a class="primary-btn center" href="subject.php"><?= t('Finish Subject Chooser →') ?></a>
 <?php endif; ?>
</section>

<section class="panel">
 <div class="panel-title"><span>◈</span><div><small><?= t('YOUR NEXT ACTION') ?></small><h2><?= t('One step at a time') ?></h2></div></div>
 <?php if ($completion['next']): ?>
   <a class="next-card" href="<?= htmlspecialchars($completion['next']['url']) ?>" style="text-decoration:none;color:inherit;display:flex">
     <span class="big-number">1</span><div><b><?= $completion['next']['label'] ?></b><p><?= t('Answer this once and Khetha uses it everywhere.') ?></p></div>
   </a>
   <p class="muted" style="font-size:12px;margin:12px 0 0"><?= t('{percent}% of your profile is filled in.', ['percent' => $completion['percent']]) ?></p>
 <?php else: ?>
   <div class="next-card">
     <span class="big-number">1</span><div><b><?= t('Compare qualification routes') ?></b><p><?= t('Your profile is complete — see different study options that can lead toward your chosen career.') ?></p></div>
   </div>
   <button class="secondary-btn" onclick="markComplete(this)" data-done="<?= t('Explored ✓') ?>"><?= t('Mark as explored ✓') ?></button>
 <?php endif; ?>
 <div class="offline-card">📥 <b><?= t('Your journey can travel with you.') ?></b><br><span><?= t('Saved journey content is available offline in the PWA prototype.') ?></span></div>
</section>
</div>
</main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
