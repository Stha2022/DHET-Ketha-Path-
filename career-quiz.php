<?php
session_start();
require_once __DIR__ . '/career-quiz-data.php';

$dimensions = cq_dimensions();
$items = cq_items();

$dimBlurbs = [
    'R' => 'You like working with your hands, tools or machines.',
    'I' => 'You like figuring out how and why things work.',
    'A' => 'You like creating things, like art, design, writing or performing.',
    'S' => 'You like helping, teaching or supporting people.',
    'E' => 'You like leading, selling or starting your own thing.',
    'C' => 'You like staying organised and keeping things in order.',
];

$results = null;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answersRaw = json_decode($_POST['answers'] ?? '', true);

    if (!is_array($answersRaw) || count($answersRaw) < 30) {
        $errors['answers'] = 'Something went wrong collecting your answers. Please try the questionnaire again.';
    } else {
        $score   = cq_score($answersRaw);
        $matches = array_slice(cq_match_occupations($score['vector']), 0, 6);

        // Group by reachability from Subject Chooser, if that's already been run.
        $subjectBase = $_SESSION['subject_tool'] ?? null;
        if ($subjectBase) {
            $reverse = sj_reverse_scan($subjectBase['selectedSubjects'], $subjectBase['marks'], $subjectBase['maths']);
            $bucketByKey = [];
            foreach ($reverse as $r) $bucketByKey[$r['key']] = $r['bucket'];
            foreach ($matches as &$m) {
                $m['bucket'] = $bucketByKey[$m['key']] ?? null;
            }
            unset($m);
        }

        $results = [
            'score' => $score,
            'matches' => $matches,
            'itemsAnswered' => count($answersRaw),
            'hasSubjectProfile' => (bool)$subjectBase,
        ];

        // Stored in session for now so Dashboard/My Path can see this
        // assessment as done and reuse the score. TODO: once
        // database/migration_assessment_results.sql is wired up, persist
        // this to assessment_results instead (see subject_tool's dashboard.php
        // comment for the same pending swap) — session stays as a cache.
        $_SESSION['career_quiz'] = [
            'answers' => $answersRaw,
            'score' => $score,
            'matches' => $matches,
            'completedAt' => time(),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Career Choice Questionnaire</title>
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4">
            <div class="mb-4">
                <h2>Career Choice Questionnaire</h2>
                <p class="text-muted">This shows what kind of work you're drawn to, not what you're good at. 30 quick questions, one at a time.</p>
            </div>

            <?php if ($results): ?>

                <?php if (!empty($errors['answers'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['answers']) ?></div>
                <?php else: ?>

                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-primary-subtle">Your code</div>
                        <div class="card-body">
                            <h3 class="mb-3"><?= htmlspecialchars($results['score']['code']) ?></h3>
                            <p class="mb-2">You lean towards these three things most:</p>
                            <ul class="mb-3">
                                <?php foreach ($results['score']['top3'] as $d): ?>
                                    <li><strong><?= htmlspecialchars($dimensions[$d]) ?>:</strong> <?= htmlspecialchars($dimBlurbs[$d]) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <p class="text-muted small mb-0">This is based on all <?= (int)$results['itemsAnswered'] ?> questions.</p>
                        </div>
                    </div>

                    <?php if (!$results['hasSubjectProfile']): ?>
                        <div class="alert alert-info">
                            Want to see which of these you could actually reach with your current subjects? <a href="subject.php" class="alert-link">Try Subject Chooser</a> and come back here.
                        </div>
                    <?php endif; ?>

                    <div class="card mb-3">
                        <div class="card-header">Careers that match your interests</div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Tap a career to see why it matched.</p>
                            <div class="row g-3">
                                <?php foreach ($results['matches'] as $m): ?>
                                    <div class="col-sm-6 col-lg-4">
                                        <button type="button" class="btn text-start w-100 h-100 p-3 border rounded-3" data-bs-toggle="modal" data-bs-target="#careerModal" data-career="<?= htmlspecialchars($m['label']) ?>" data-field="<?= htmlspecialchars($m['field']) ?>" data-match="<?= (int)$m['match'] ?>" data-reason="<?= htmlspecialchars($m['reason']) ?>">
                                            <div class="fw-semibold mb-1"><?= htmlspecialchars($m['label']) ?></div>
                                            <div class="text-muted small mb-2"><?= htmlspecialchars($m['field']) ?></div>
                                            <div class="fw-bold text-primary mb-2"><?= htmlspecialchars(cq_match_label((int)$m['match'])) ?></div>
                                            <?php if ($results['hasSubjectProfile']): ?>
                                                <?php
                                                    $bucket = $m['bucket'] ?? null;
                                                    $badge = ['open' => 'success', 'effort' => 'warning', 'closed' => 'secondary'][$bucket] ?? 'light border';
                                                    $label = ['open' => 'Open', 'effort' => 'With effort', 'closed' => 'Closed'][$bucket] ?? 'Unknown';
                                                ?>
                                                <span class="badge text-bg-<?= $badge ?>"><?= $label ?></span>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="careerModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="careerModalLabel"></h5>
                                        <span class="badge text-bg-light border" id="careerModalField"></span>
                                        <span class="badge text-bg-primary" id="careerModalMatch"></span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div id="careerModalLoading" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Generating insights for this career…
                                    </div>
                                    <div id="careerModalContent" class="d-none">
                                        <p class="small text-muted fst-italic">This is placeholder content. The full version will generate it with AI from approved NCAP/DHET sources.</p>
                                        <h6>Why it matched</h6>
                                        <p id="careerModalReason"></p>
                                        <h6>About this career</h6>
                                        <p id="careerModalAbout"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <a href="career-quiz.php" class="btn btn-outline-secondary">Start over</a>

            <?php else: ?>

                <div class="card">
                    <div class="card-body text-center py-5" id="quizStart">
                        <p class="text-muted mb-4">30 short questions, one at a time.</p>
                        <button type="button" class="btn btn-primary btn-lg" id="startBtn">Start</button>
                        <div>
                            <button type="button" class="btn btn-link d-none mt-2" id="resumeBtn"></button>
                        </div>
                    </div>

                    <div class="card-body text-center py-5 d-none" id="quizPaused">
                        <p class="mb-1">Your progress is saved.</p>
                        <p class="text-muted mb-4">Come back anytime and pick up right where you left off.</p>
                        <button type="button" class="btn btn-primary" id="resumeFromPauseBtn">Resume now</button>
                    </div>

                    <div class="card-body d-none" id="quizBody">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <p class="text-muted small mb-0" id="quizCounter"></p>
                            <button type="button" class="btn btn-link btn-sm p-0" id="pauseBtn">Save &amp; finish later</button>
                        </div>
                        <div class="progress mb-4" style="height:4px">
                            <div class="progress-bar" id="quizProgress" style="width:0%"></div>
                        </div>

                        <p class="text-muted mb-2">Would you enjoy…</p>
                        <h4 class="mb-4" id="quizItemText"></h4>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-secondary py-3 quiz-answer" data-rating="3">Yes</button>
                            <button type="button" class="btn btn-outline-secondary py-3 quiz-answer" data-rating="2">Maybe</button>
                            <button type="button" class="btn btn-outline-secondary py-3 quiz-answer" data-rating="1">No</button>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="prevBtn">Previous question</button>
                    </div>
                </div>

                <form method="post" action="career-quiz.php" id="quizForm" class="d-none">
                    <input type="hidden" name="answers" id="answersInput">
                </form>

            <?php endif; ?>
        </div>

        <?php if (!$results): ?>
        <script>
        (function(){
            var items = <?= json_encode($items) ?>;

            var STORAGE_KEY = 'khetha_career_quiz_progress';
            var currentIndex = 0;
            var answers = {};

            var startBtn = document.getElementById('startBtn');
            var resumeBtn = document.getElementById('resumeBtn');
            var resumeFromPauseBtn = document.getElementById('resumeFromPauseBtn');
            var pauseBtn = document.getElementById('pauseBtn');
            var prevBtn = document.getElementById('prevBtn');
            var quizStart = document.getElementById('quizStart');
            var quizPaused = document.getElementById('quizPaused');
            var quizBody = document.getElementById('quizBody');
            var counter = document.getElementById('quizCounter');
            var progress = document.getElementById('quizProgress');
            var itemText = document.getElementById('quizItemText');
            var answerBtns = document.querySelectorAll('.quiz-answer');

            function loadSaved() {
                try {
                    var raw = localStorage.getItem(STORAGE_KEY);
                    if (!raw) return null;
                    var data = JSON.parse(raw);
                    if (typeof data.currentIndex !== 'number' || typeof data.answers !== 'object') return null;
                    return data;
                } catch (e) { return null; }
            }

            function saveProgress() {
                try { localStorage.setItem(STORAGE_KEY, JSON.stringify({ currentIndex: currentIndex, answers: answers })); } catch (e) {}
            }

            function clearSaved() {
                try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
            }

            var saved = loadSaved();
            if (saved && resumeBtn) {
                resumeBtn.classList.remove('d-none');
                resumeBtn.textContent = 'Resume where you left off (question ' + (saved.currentIndex + 1) + ' of ' + items.length + ')';
            }

            function showItem(i) {
                itemText.textContent = items[i].text;
                counter.textContent = 'Question ' + (i + 1);
                progress.style.width = Math.round(((i + 1) / items.length) * 100) + '%';
                prevBtn.disabled = i === 0;

                // Without the blur, the button tapped for the previous
                // question keeps its focus/active styling on mobile, making
                // the same position look pre-selected on the new question.
                answerBtns.forEach(function(b) {
                    b.blur();
                    b.classList.remove('active', 'btn-primary');
                    b.classList.add('btn-outline-secondary');
                });
                if (document.activeElement && document.activeElement.blur) document.activeElement.blur();

                // Went back to a question already answered, so show what was picked.
                var existing = answers[items[i].id];
                if (existing) {
                    var match = document.querySelector('.quiz-answer[data-rating="' + existing + '"]');
                    if (match) { match.classList.remove('btn-outline-secondary'); match.classList.add('btn-primary'); }
                }
            }

            function finish() {
                clearSaved();
                document.getElementById('answersInput').value = JSON.stringify(answers);
                document.getElementById('quizForm').submit();
            }

            function answer(rating) {
                var item = items[currentIndex];
                answers[item.id] = rating;
                currentIndex++;
                saveProgress();
                if (currentIndex >= items.length) { finish(); return; }
                showItem(currentIndex);
            }

            function goBack() {
                if (currentIndex === 0) return;
                currentIndex--;
                saveProgress();
                showItem(currentIndex);
            }

            function begin(fromSaved) {
                quizStart.classList.add('d-none');
                quizPaused.classList.add('d-none');
                quizBody.classList.remove('d-none');
                if (fromSaved) {
                    currentIndex = fromSaved.currentIndex;
                    answers = fromSaved.answers;
                }
                showItem(currentIndex);
            }

            startBtn.addEventListener('click', function(){
                clearSaved();
                currentIndex = 0;
                answers = {};
                begin(false);
            });

            if (resumeBtn) resumeBtn.addEventListener('click', function(){ begin(saved); });
            if (resumeFromPauseBtn) resumeFromPauseBtn.addEventListener('click', function(){ begin(loadSaved() || { currentIndex: currentIndex, answers: answers }); });

            prevBtn.addEventListener('click', goBack);

            pauseBtn.addEventListener('click', function(){
                saveProgress();
                quizBody.classList.add('d-none');
                quizPaused.classList.remove('d-none');
            });

            answerBtns.forEach(function(btn){
                btn.addEventListener('click', function(){ answer(+btn.dataset.rating); });
            });
        })();
        </script>
        <?php else: ?>
        <script>
        (function(){
            var careerModal = document.getElementById('careerModal');
            if (!careerModal) return;
            careerModal.addEventListener('show.bs.modal', function(event){
                var row = event.relatedTarget;
                var career = row.dataset.career;
                var field = row.dataset.field;
                var match = +row.dataset.match;
                var reason = row.dataset.reason;
                var matchLabel = match >= 80 ? 'Strong match' : (match >= 60 ? 'Good match' : 'Possible match');

                document.getElementById('careerModalLabel').textContent = career;
                document.getElementById('careerModalField').textContent = field;
                document.getElementById('careerModalMatch').textContent = matchLabel;

                var loading = document.getElementById('careerModalLoading');
                var content = document.getElementById('careerModalContent');
                loading.classList.remove('d-none');
                content.classList.add('d-none');

                setTimeout(function(){
                    document.getElementById('careerModalReason').textContent = reason + '. Your answers lined up closely with what this career tends to involve.';
                    document.getElementById('careerModalAbout').textContent =
                        'Placeholder text. Here Khetha\'s AI will explain, in plain language, what a ' + career + ' actually does day to day in ' + field + '.';
                    loading.classList.add('d-none');
                    content.classList.remove('d-none');
                }, 700);
            });
        })();
        </script>
        <?php endif; ?>
    </body>
</html>
