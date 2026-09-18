<?php
session_start();
require_once __DIR__ . '/assessment-relevance-data.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$u = $_SESSION['user'];
$first = explode(' ', $u['name'])[0];
$grade = $u['grade'] ?? AR_FALLBACK_GRADE;

// Progress signal for each assessment. The real source of truth is
// assessment_results (database/migration_assessment_results.sql) once
// subject.php, career-quiz.php and occupation.php are wired to write to
// it — that wiring hasn't happened yet, so job_fit always reads as
// not-started for now. subject_chooser and career_choice already have
// working interim signals: subject.php stores $_SESSION['subject_tool']
// and career-quiz.php stores $_SESSION['career_quiz'] on completion.
$subjectDone = isset($_SESSION['subject_tool']);
$careerQuizDone = isset($_SESSION['career_quiz']);

$assessmentDefs = [
    AR_ASSESSMENT_SUBJECT_CHOOSER => ['href' => 'subject.php', 'icon' => 'bi-journal-bookmark', 'title' => 'Subject Chooser', 'desc' => 'Explore subjects for intended career and study pathways.', 'done' => $subjectDone],
    AR_ASSESSMENT_CAREER_CHOICE   => ['href' => 'career-quiz.php', 'icon' => 'bi-signpost-split', 'title' => 'Career Choice', 'desc' => 'Mobile-first career self-assessment.', 'done' => $careerQuizDone],
    AR_ASSESSMENT_JOB_FIT         => ['href' => 'occupation.php', 'icon' => 'bi-briefcase', 'title' => 'Job Fit', 'desc' => 'Explore occupations and check your fit.', 'done' => false],
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
$doneCount = count(array_filter($assessments, fn($a) => $a['done']));
$nextIncomplete = null;
foreach ($assessments as $a) { if (!$a['done']) { $nextIncomplete = $a; break; } }

// Next-step copy names the actual next action for this user's state
// instead of a generic line.
if ($doneCount === 0) {
    $nextStep = ['title' => 'Start with Subject Chooser', 'body' => "It's the fastest of the three and shapes what the other two can tell you.", 'href' => 'subject.php'];
} elseif ($doneCount < count($assessments)) {
    $verb = $doneCount === count($assessments) - 1 ? 'Finish with' : 'Try';
    $nextStep = ['title' => $verb . ' ' . $nextIncomplete['title'], 'body' => $nextIncomplete['desc'], 'href' => $nextIncomplete['href']];
} else {
    $nextStep = ['title' => 'See your full picture in My Path', 'body' => 'All three assessments are in — My Path pulls them together.', 'href' => 'my-path.php'];
}

$secondaryTiles = [
    ['href' => 'directories.php', 'icon' => 'bi-search', 'title' => 'Directories', 'desc' => 'Careers, qualifications & providers.'],
    ['href' => 'what-if.php', 'icon' => 'bi-shuffle', 'title' => 'What If?', 'desc' => 'Change a scenario and adapt your route.'],
    ['href' => 'advice.php', 'icon' => 'bi-telephone', 'title' => 'Career Advice', 'desc' => 'Reach a human practitioner when needed.'],
    ['href' => 'favourites.php', 'icon' => 'bi-heart', 'title' => 'My Favourites', 'desc' => 'Saved careers, qualifications and providers.'],
];
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Khetha Dashboard</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <main class="dashboard dashboard-lite container">

            <section class="hero-greeting">
                <div class="hero-greeting-copy">
                    <p class="eyebrow">MY KHETHA</p>
                    <h1>Hi, <?= htmlspecialchars($first) ?>! 👋</h1>
                    <p class="hero-tagline">Your journey is personalised around your level, subjects and interests.</p>
                    <a class="primary-btn hero-cta" href="<?= htmlspecialchars($nextStep['href']) ?>"><?= htmlspecialchars($nextStep['title']) ?> <span>&rarr;</span></a>
                </div>
                <div class="greeting-video-wrap" aria-label="Khetha greeting">
                    <video class="greeting-video" autoplay muted loop playsinline preload="auto" poster="assets/images/khetha-logo.png">
                        <source src="assets/media/khetha-greeting.mp4" type="video/mp4">
                    </video>
                    <div class="video-caption">Hi! I'm Khetha 👋</div>
                </div>
            </section>

            <section class="quick-actions" aria-label="Quick actions">
                <a href="subject.php"><span>📚</span><b>Subjects</b></a>
                <a href="career-quiz.php"><span>🧭</span><b>Careers</b></a>
                <a href="my-path.php"><span>🛣️</span><b>My Path</b></a>
                <a href="ask.php"><span>✨</span><b>Ask Khetha</b></a>
            </section>

            <a href="<?= htmlspecialchars($nextStep['href']) ?>" class="card mb-4 text-decoration-none text-body border-primary mt-2">
                <div class="card-body d-flex justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-map fs-3 text-primary"></i>
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($nextStep['title']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($nextStep['body']) ?></div>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            </a>

            <h6 class="text-uppercase text-muted small mb-2">Assessments</h6>
            <div class="row g-3 mb-4">
                <?php foreach ($assessments as $a): ?>
                    <?php $isTertiary = $a['priority'] === AR_PRIORITY_TERTIARY; ?>
                    <div class="col-12 col-md-4">
                        <a href="<?= htmlspecialchars($a['href']) ?>" class="card h-100 text-decoration-none text-body <?= $isTertiary ? 'border' : 'border-primary border-2' ?>">
                            <div class="card-body <?= $isTertiary ? 'opacity-75' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <i class="bi <?= $a['icon'] ?> fs-3 <?= $isTertiary ? 'text-muted' : 'text-primary' ?>"></i>
                                    <?php if ($a['done']): ?>
                                        <span class="badge text-bg-success">Done</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border">Not started</span>
                                    <?php endif; ?>
                                </div>
                                <h5 class="mb-1"><?= htmlspecialchars($a['title']) ?></h5>
                                <?php if ($a['hint']): ?>
                                    <p class="text-muted small fst-italic mb-1"><?= htmlspecialchars($a['hint']) ?></p>
                                <?php endif; ?>
                                <p class="text-muted small mb-0"><?= htmlspecialchars($a['desc']) ?></p>
                                <?php if ($a['done']): ?>
                                    <span class="small text-primary fw-semibold d-inline-block mt-2">Retake &rarr;</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <h6 class="text-uppercase text-muted small mb-2">More</h6>
            <div class="row g-2">
                <?php foreach ($secondaryTiles as $t): ?>
                    <div class="col-12 col-sm-6 col-md-3">
                        <a href="<?= htmlspecialchars($t['href']) ?>" class="card h-100 text-decoration-none text-body">
                            <div class="card-body py-3">
                                <i class="bi <?= $t['icon'] ?> text-muted mb-2 d-block"></i>
                                <div class="small fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($t['desc']) ?></div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="offline-hint">📥 Your Khetha journey is designed for low-data use and can keep key content available offline.</p>

        </main>
    </body>
</html>
