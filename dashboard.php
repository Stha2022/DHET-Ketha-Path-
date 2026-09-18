<?php
session_start();
require_once __DIR__ . '/assessment-relevance-data.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/profile.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

// Everything below comes from the shared learner profile.
$profile = profile_get();
$completion = profile_completion();
$steps = $completion['steps'];
$first = explode(' ', $profile['name'] !== '' ? $profile['name'] : $_SESSION['user']['name'])[0];
$grade = $profile['grade'] !== '' ? $profile['grade'] : AR_FALLBACK_GRADE;

// Real status per assessment, from what the profile holds:
//   done      the step is complete
//   progress  something was saved but not everything (Subject Chooser: languages given, no careers picked)
//   todo      nothing yet
$intendedCount = count($profile['intended_careers']);
$fitCount = count($profile['job_fit']);
$subjectStarted = $profile['home_language'] !== '' || $profile['fal'] !== '';

$assessmentDefs = [
    AR_ASSESSMENT_SUBJECT_CHOOSER => [
        'href' => 'subject.php', 'doneLabel' => 'View results', 'icon' => 'bi-journal-bookmark', 'title' => t('Subject Chooser'),
        'desc' => t('Explore subjects for intended career and study pathways.'),
        'status' => $steps['subject_chooser']['done'] ? 'done' : ($subjectStarted ? 'progress' : 'todo'),
        'detail' => $steps['subject_chooser']['done'] ? ($intendedCount === 1 ? t('1 career picked') : t('{n} careers picked', ['n' => $intendedCount])) : ($subjectStarted ? t('Pick the careers you are aiming for') : ''),
    ],
    AR_ASSESSMENT_CAREER_CHOICE => [
        'href' => 'career-quiz.php', 'doneLabel' => 'View results', 'icon' => 'bi-signpost-split', 'title' => t('Career Choice'),
        'desc' => t('Mobile-first career self-assessment.'),
        'status' => $steps['career_choice']['done'] ? 'done' : 'todo',
        'detail' => $steps['career_choice']['done'] ? t('Your code: {code}', ['code' => $profile['career_quiz']['code']]) : '',
    ],
    AR_ASSESSMENT_JOB_FIT => [
        'href' => 'occupation.php', 'doneLabel' => 'Check more careers', 'icon' => 'bi-briefcase', 'title' => t('Job Fit'),
        'desc' => t('Explore occupations and check your fit.'),
        'status' => $steps['job_fit']['done'] ? 'done' : 'todo',
        'detail' => $steps['job_fit']['done'] ? ($fitCount === 1 ? t('1 career checked') : t('{n} careers checked', ['n' => $fitCount])) : '',
    ],
];

// Rendered in grade-priority order (see assessment-relevance-data.php)
// instead of a fixed order, so e.g. Career Choice leads Subject Chooser
// for Grade 9/10 and Job Fit leads for Grade 11/12.
$assessments = [];
foreach (ar_ordered_assessments($grade) as $key) {
    $a = $assessmentDefs[$key];
    $a['priority'] = ar_priority_for($grade, $key);
    $a['hint'] = $a['priority'] === AR_PRIORITY_TERTIARY ? ar_tertiary_hint($grade, $key) : null;
    $assessments[] = $a;
}

// The highlighted button: the first unfinished step, or My Path once all five are done.
$next = $completion['next'] ?? ['label' => t('See your full picture in My Path'), 'url' => 'my-path.php'];
$nextLabel = $completion['next'] ? t('Next: {label}', ['label' => $next['label']]) : $next['label'];

// Quick stats. Subjects counts the Maths track as a subject.
$subjectCount = count($profile['subjects']) + ($profile['maths_track'] !== '' ? 1 : 0);
$stats = [
    ['label' => t('Subjects'), 'value' => $subjectCount],
    ['label' => t('Interests'), 'value' => count($profile['interests'])],
    ['label' => t('Career Choice'), 'value' => $profile['career_quiz']['code'] !== '' ? $profile['career_quiz']['code'] : '—'],
];

$secondaryTiles = [
    ['href' => 'directories.php', 'icon' => 'bi-search', 'title' => t('Browse'), 'desc' => t('Careers, qualifications &amp; providers.')],
    ['href' => 'what-if.php', 'icon' => 'bi-shuffle', 'title' => t('What If?'), 'desc' => t('Change a scenario and adapt your route.')],
    ['href' => 'advice.php', 'icon' => 'bi-telephone', 'title' => t('Career Advice'), 'desc' => t('Reach a human practitioner when needed.')],
    ['href' => 'cv.php', 'icon' => 'bi-file-earmark-person', 'title' => t('My CV'), 'desc' => t('A CV started for you from what Khetha knows.')],
    ['href' => 'favourites.php', 'icon' => 'bi-heart', 'title' => t('My Favourites'), 'desc' => t('Saved careers, qualifications and providers.')],
];
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <title><?= t('My Khetha Dashboard') ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <main class="dashboard dashboard-lite container">

            <section class="hero-greeting">
                <div class="hero-greeting-copy">
                    <p class="eyebrow"><?= t('MY KHETHA') ?></p>
                    <h1><?= t('Hi, {name}!', ['name' => $first]) ?> 👋</h1>
                    <p class="hero-tagline"><?= t('Your journey is personalised around your level, subjects and interests.') ?></p>
                    <a class="primary-btn hero-cta" href="<?= htmlspecialchars($next['url']) ?>"><?= $nextLabel ?> <span>&rarr;</span></a>
                </div>
                <div class="greeting-video-wrap" aria-label="<?= t('Khetha greeting') ?>">
                    <video class="greeting-video" autoplay muted loop playsinline preload="auto" poster="assets/images/khetha-logo.png">
                        <source src="assets/media/khetha-greeting.mp4" type="video/mp4">
                    </video>
                    <div class="video-caption"><?= t('Hi! I’m Khetha') ?> 👋</div>
                </div>
            </section>

            <section class="card mb-3 mt-2" aria-label="<?= t('Your progress') ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <h6 class="mb-0"><?= t('Your progress') ?></h6>
                        <span class="fw-bold fs-5" id="progressPercent"><?= (int)$completion['percent'] ?>%</span>
                    </div>
                    <div class="progress mb-3" style="height:10px" role="progressbar" aria-label="<?= t('Your progress') ?>" aria-valuenow="<?= (int)$completion['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width:<?= (int)$completion['percent'] ?>%"></div>
                    </div>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0 small">
                        <?php foreach ($steps as $key => $s): ?>
                            <li>
                                <a href="<?= htmlspecialchars($s['url']) ?>" data-step="<?= $key ?>" class="badge rounded-pill text-decoration-none <?= $s['done'] ? 'text-bg-success' : 'text-bg-light border text-body' ?>">
                                    <i class="bi <?= $s['done'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i> <?= $s['label'] ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </section>

            <section class="row g-2 mb-3" aria-label="<?= t('Quick stats') ?>">
                <?php foreach ($stats as $st): ?>
                    <div class="col-4">
                        <div class="card h-100 text-center">
                            <div class="card-body py-3">
                                <div class="fs-4 fw-bold lh-1 mb-1"><?= htmlspecialchars((string)$st['value']) ?></div>
                                <div class="text-muted small"><?= $st['label'] ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="quick-actions" aria-label="<?= t('Quick actions') ?>">
                <a href="subject.php"><span>📚</span><b><?= t('Subjects') ?></b></a>
                <a href="career-quiz.php"><span>🧭</span><b><?= t('Careers') ?></b></a>
                <a href="my-path.php"><span>🛣️</span><b><?= t('My Path') ?></b></a>
                <a href="ask.php"><span>✨</span><b><?= t('Ask Khetha') ?></b></a>
            </section>

            <h6 class="text-uppercase text-muted small mb-2 mt-3"><?= t('Assessments') ?></h6>
            <div class="row g-3 mb-4">
                <?php foreach ($assessments as $a): ?>
                    <?php
                        $isTertiary = $a['priority'] === AR_PRIORITY_TERTIARY;
                        [$badgeClass, $badgeText] = [
                            'done' => ['text-bg-success', t('Done')],
                            'progress' => ['text-bg-warning', t('In progress')],
                            'todo' => ['text-bg-light border', t('Not started')],
                        ][$a['status']];
                    ?>
                    <div class="col-12 col-md-4">
                        <a href="<?= htmlspecialchars($a['href']) ?>" class="card h-100 text-decoration-none text-body <?= $isTertiary ? 'border' : 'border-primary border-2' ?>">
                            <div class="card-body <?= $isTertiary ? 'opacity-75' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <i class="bi <?= $a['icon'] ?> fs-3 <?= $isTertiary ? 'text-muted' : 'text-primary' ?>"></i>
                                    <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                </div>
                                <h5 class="mb-1"><?= $a['title'] ?></h5>
                                <?php if ($a['hint']): ?>
                                    <p class="text-muted small fst-italic mb-1"><?= htmlspecialchars($a['hint']) ?></p>
                                <?php endif; ?>
                                <p class="text-muted small mb-0"><?= $a['desc'] ?></p>
                                <?php if ($a['detail']): ?>
                                    <p class="small fw-semibold mb-0 mt-2"><?= $a['detail'] ?></p>
                                <?php endif; ?>
                                <?php if ($a['status'] !== 'todo'): ?>
                                    <span class="small text-primary fw-semibold d-inline-block mt-2"><?= $a['status'] === 'done' ? t($a['doneLabel']) : t('Continue') ?> &rarr;</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <h6 class="text-uppercase text-muted small mb-2"><?= t('More') ?></h6>
            <div class="row g-2">
                <?php foreach ($secondaryTiles as $t): ?>
                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="<?= htmlspecialchars($t['href']) ?>" class="card h-100 text-decoration-none text-body">
                            <div class="card-body py-3">
                                <i class="bi <?= $t['icon'] ?> text-muted mb-2 d-block"></i>
                                <div class="small fw-semibold"><?= $t['title'] ?></div>
                                <div class="text-muted small"><?= $t['desc'] ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="offline-hint">📥 <?= t('Your Khetha journey is designed for low-data use and can keep key content available offline.') ?></p>

        </main>
    </body>
</html>
