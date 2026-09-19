<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/job-fit-data.php';
require_once __DIR__ . '/includes/profile.php';
kp_require_auth();
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/journey.php';

$occupations = kp_occupations();

$method = $_SERVER['REQUEST_METHOD'];
$isSubmit = $method === 'POST' && ($_POST['action'] ?? '') === 'fit_submit';

$id = trim((string)($isSubmit ? ($_POST['occupation_id'] ?? '') : ($_GET['id'] ?? '')));
$occupation = $id !== '' ? kp_occupation($id) : null;
$showFit = $isSubmit || isset($_GET['fit']);
$old = $isSubmit ? $_POST : [];

$results = null;
$errors = [];
$csrfFailed = false;

// A Job Fit check is saved to the shared profile, one entry per occupation
// (a broad check scores every occupation, so it saves one for each).
$saveFit = fn(array $detail, string $mode) => [
    'overall' => $detail['overall'], 'flags' => $detail['flags'], 'completed_at' => time(), 'mode' => $mode,
];

if ($isSubmit) {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $csrfFailed = true; // the questionnaire re-opens with the answers still filled in
    } else {
        $parsed = jf_parse_answers($_POST);
        $errors = $parsed['errors'];
        if (empty($errors)) {
            if ($occupation) {
                $detail = jf_score_occupation($parsed['answers'], $occupation);
                $results = ['mode' => 'targeted', 'detail' => $detail];
                profile_update(['job_fit' => [$occupation['id'] => $saveFit($detail, 'targeted')]]);
                kp_record_assessment(kp_user_id(), 'job_fit', ['occupation_id'=>$occupation['id']], ['occupation_id'=>$occupation['id'],'overall'=>$detail['overall'],'flags'=>$detail['flags']]);
                kp_log_event(kp_user_id(),'job_fit_completed',['occupation_id'=>$occupation['id']]);
            } else {
                $matches = jf_match_occupations($parsed['answers']);
                $results = ['mode' => 'broad', 'matches' => $matches];
                $toSave = [];
                foreach ($matches as $m) $toSave[$m['occupation_id']] = $saveFit($m, 'broad');
                profile_update(['job_fit' => $toSave]);
                foreach ($matches as $m) kp_record_assessment(kp_user_id(), 'job_fit', ['occupation_id'=>$m['occupation_id']], ['occupation_id'=>$m['occupation_id'],'overall'=>$m['overall'],'flags'=>$m['flags']]);
                kp_log_event(kp_user_id(),'job_fit_completed',['mode'=>'broad','matches'=>count($matches)]);
            }
        }
    }
}

// Already checked this occupation? Opening its Job Fit link shows the saved
// result rather than an empty form; ?redo=1 (the "Redo" link) starts again.
$profile = profile_get();
$savedFit = $occupation ? ($profile['job_fit'][$occupation['id']] ?? null) : null;
if (!$results && !$isSubmit && $savedFit && isset($_GET['fit']) && !isset($_GET['redo'])) {
    $results = [
        'mode' => 'targeted', 'saved' => true,
        'detail' => [
            'occupation_id' => $occupation['id'], 'title' => $occupation['title'], 'field' => $occupation['field'],
            'overall' => (int)$savedFit['overall'], 'flags' => (array)$savedFit['flags'],
        ],
        'completedAt' => $savedFit['completed_at'] ?? null,
    ];
}

// "Suggested for you": careers the learner already picked, then the ones
// Career Choice pointed to, without repeats.
$suggested = [];
foreach ($profile['intended_careers'] as $sid) $suggested[$sid] = 'Your pick';
foreach ($profile['career_quiz']['top_careers'] as $sid) $suggested[$sid] ??= 'From Career Choice';
$suggested = array_slice(array_intersect_key($suggested, $occupations), 0, 6, true);

$valueLabels = kp_work_value_keys();
$valueBlurbs = jf_work_value_blurbs();
$contextLabels = kp_work_context_keys();
$contextBlurbs = jf_work_context_blurbs();
$aptitudeLabels = kp_aptitude_keys();
$aptitudeBlurbs = jf_aptitude_blurbs();
$scale = array_reverse(jf_scale(), true); // Yes first, then Maybe, then No
$constraintFields = jf_constraint_fields();

function jf_score_class(int $score): string {
    return $score >= 75 ? 'success' : ($score >= 45 ? 'warning' : 'danger');
}
function jf_verdict_label(int $score): string {
    return $score >= 75 ? 'Strong fit' : ($score >= 45 ? 'Partial fit' : 'Weak fit');
}
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <title>Occupations &amp; Job Fit</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4">

            <?php if ($results): ?>
                <?php // ============================== RESULTS ============================== ?>

                <div class="mb-4">
                    <h2>Job Fit results</h2>
                    <p class="text-muted">Can you actually do and sustain this — not just would you enjoy it.</p>
                </div>

                <?php if ($results['mode'] === 'targeted'): $detail = $results['detail']; ?>

                    <div class="card mb-3">
                        <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <p class="text-muted small mb-1"><?= htmlspecialchars($detail['field']) ?></p>
                                    <h3 class="mb-0"><?= htmlspecialchars($detail['title']) ?></h3>
                                </div>
                                <div class="text-end">
                                    <div class="display-6 fw-bold text-<?= jf_score_class($detail['overall']) ?> lh-1"><?= jf_verdict_label($detail['overall']) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($results['saved']) && !empty($results['completedAt'])): ?>
                                <p class="text-muted small mb-0 mt-3">Your saved result from <?= date('j M Y', (int)$results['completedAt']) ?>.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="mb-2">Worth weighing</h6>
                            <?php if (!empty($detail['flags'])): ?>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($detail['flags'] as $flag): ?><li class="mb-1"><?= htmlspecialchars($flag) ?></li><?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No major mismatches based on what you told us.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid d-sm-flex gap-2">
                        <a href="occupation.php?id=<?= urlencode($detail['occupation_id']) ?>&amp;fit=1&amp;redo=1" class="btn btn-outline-secondary">Redo</a>
                        <a href="occupation.php" class="btn btn-outline-secondary">Back to careers</a>
                        <a href="occupation.php?fit=1" class="btn btn-primary">Run a broad check instead</a>
                    </div>

                <?php else: // broad ?>

                    <div class="card mb-3">
                        <div class="card-header">Your best occupation fits</div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">Ranked by overall fit across all careers. Tap one for the full breakdown.</p>
                            <div class="row g-3">
                                <?php foreach (array_slice($results['matches'], 0, 12) as $m): ?>
                                    <div class="col-sm-6 col-lg-4">
                                        <button type="button" class="btn text-start w-100 h-100 p-3 border rounded-3" data-bs-toggle="modal" data-bs-target="#fitModal" data-occupation="<?= htmlspecialchars($m['occupation_id']) ?>">
                                            <div class="fw-semibold mb-1"><?= htmlspecialchars($m['title']) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($m['field']) ?></div>
                                            <div class="fw-bold mt-1 text-<?= jf_score_class($m['overall']) ?>"><?= jf_verdict_label($m['overall']) ?></div>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="fitModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="fitModalLabel"></h5>
                                        <span class="badge text-bg-light border" id="fitModalField"></span>
                                        <span class="badge" id="fitModalScore"></span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="fitModalBody"></div>
                            </div>
                        </div>
                    </div>

                    <script id="fitMatchData" type="application/json"><?= json_encode($results['matches']) ?></script>

                    <div class="d-grid d-sm-flex gap-2">
                        <a href="occupation.php?fit=1" class="btn btn-outline-secondary">Start over</a>
                        <a href="occupation.php" class="btn btn-outline-secondary">Back to careers</a>
                    </div>

                <?php endif; ?>

            <?php elseif ($showFit): ?>
                <?php // ============================== QUESTIONNAIRE ============================== ?>

                <div class="mb-4">
                    <h2>Job Fit Questionnaire</h2>
                    <p class="text-muted">This isn't the "what would you enjoy" quiz &mdash; that's Career Choice. This one asks what you can actually do and sustain.</p>
                </div>

                <?php if ($csrfFailed): ?><div class="alert alert-danger">Your session expired before your answers could be saved. Your answers are still filled in below. Sign in again if needed, then tap "See my results".</div><?php endif; ?>
                <?php if (!empty($errors)): ?><div class="alert alert-danger">Please answer everything below before continuing.</div><?php endif; ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <?php if ($occupation): ?>
                            <p class="text-muted small mb-1">Checking your fit for</p>
                            <h4 class="mb-0"><?= htmlspecialchars($occupation['title']) ?> <span class="badge text-bg-light border"><?= htmlspecialchars($occupation['field']) ?></span></h4>
                        <?php else: ?>
                            <?php if ($id !== ''): ?><p class="text-muted small mb-2">We couldn't find that occupation, so this will run as a broad check across all careers.</p><?php endif; ?>
                            <h4 class="mb-0">Broad Job Fit check</h4>
                            <p class="text-muted small mb-0">We'll rank every occupation in our list against your answers.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2" id="fitStepBadges">
                    <?php foreach (['Values', 'Work context', 'Aptitudes', 'Practical'] as $i => $label): ?>
                        <div class="text-center flex-fill">
                            <span class="badge rounded-pill text-bg-light border" data-badge="<?= $i + 1 ?>"><?= $i + 1 ?></span>
                            <div class="small text-muted d-none d-sm-block"><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="progress mb-4" style="height:6px" role="progressbar">
                    <div class="progress-bar" id="fitProgressBar" style="width:25%"></div>
                </div>

                <form method="post" action="occupation.php" id="fitForm" novalidate>
                    <input type="hidden" name="action" value="fit_submit">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="occupation_id" value="<?= htmlspecialchars($id) ?>">

                    <div class="card mb-3 fit-step" data-step="1">
                        <div class="card-body">
                            <h5 class="card-title mb-3">1. What matters to you in a job?</h5>

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <p class="text-muted small mb-0" id="valueCounter"></p>
                            </div>
                            <div class="progress mb-4" style="height:4px"><div class="progress-bar" id="valueProgress" style="width:0%"></div></div>

                            <?php $vi = 0; foreach ($valueLabels as $key => $label): $vi++; ?>
                                <div class="value-q <?= $vi === 1 ? '' : 'd-none' ?>" data-index="<?= $vi ?>">
                                    <p class="text-muted mb-2">Is this important to you in a job?</p>
                                    <h4 class="mb-4">A job that <?= htmlspecialchars($valueBlurbs[$key]) ?></h4>
                                    <div class="d-grid gap-2">
                                        <?php foreach ($scale as $val => $scaleLabel): ?>
                                            <input type="radio" class="btn-check" name="value_rating[<?= $key ?>]" id="val-<?= $key ?>-<?= $val ?>" value="<?= $val ?>" autocomplete="off" <?= (int)($old['value_rating'][$key] ?? 0) === $val ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-secondary py-3" for="val-<?= $key ?>-<?= $val ?>"><?= htmlspecialchars($scaleLabel) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="valuePrevBtn">Previous question</button>
                        </div>
                    </div>

                    <div class="card mb-3 fit-step d-none" data-step="2">
                        <div class="card-body">
                            <h5 class="card-title mb-3">2. How would each of these actually feel, day to day?</h5>

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <p class="text-muted small mb-0" id="contextCounter"></p>
                            </div>
                            <div class="progress mb-4" style="height:4px"><div class="progress-bar" id="contextProgress" style="width:0%"></div></div>

                            <?php $ci = 0; foreach ($contextLabels as $key => $label): $ci++; ?>
                                <div class="context-q <?= $ci === 1 ? '' : 'd-none' ?>" data-index="<?= $ci ?>">
                                    <p class="text-muted mb-2">Would this bother you in a job? Be honest, not aspirational.</p>
                                    <h4 class="mb-4"><?= htmlspecialchars(ucfirst($contextBlurbs[$key])) ?></h4>
                                    <div class="d-grid gap-2">
                                        <?php foreach ($scale as $val => $scaleLabel): ?>
                                            <input type="radio" class="btn-check" name="context[<?= $key ?>]" id="ctx-<?= $key ?>-<?= $val ?>" value="<?= $val ?>" autocomplete="off" <?= (int)($old['context'][$key] ?? 0) === $val ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-secondary py-3" for="ctx-<?= $key ?>-<?= $val ?>"><?= htmlspecialchars($scaleLabel) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="contextPrevBtn">Previous question</button>
                        </div>
                    </div>

                    <div class="card mb-3 fit-step d-none" data-step="3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">3. Rate yourself honestly on each</h5>

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <p class="text-muted small mb-0" id="aptitudeCounter"></p>
                            </div>
                            <div class="progress mb-4" style="height:4px"><div class="progress-bar" id="aptitudeProgress" style="width:0%"></div></div>

                            <?php $ai = 0; foreach ($aptitudeLabels as $key => $label): $ai++; ?>
                                <div class="aptitude-q <?= $ai === 1 ? '' : 'd-none' ?>" data-index="<?= $ai ?>">
                                    <p class="text-muted mb-2">Are you good at this? Not what you'd like to be &mdash; what you actually are, right now.</p>
                                    <h4 class="mb-4"><?= htmlspecialchars(ucfirst($aptitudeBlurbs[$key])) ?></h4>
                                    <div class="d-grid gap-2">
                                        <?php foreach ($scale as $val => $scaleLabel): ?>
                                            <input type="radio" class="btn-check" name="aptitude[<?= $key ?>]" id="apt-<?= $key ?>-<?= $val ?>" value="<?= $val ?>" autocomplete="off" <?= (int)($old['aptitude'][$key] ?? 0) === $val ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-secondary py-3" for="apt-<?= $key ?>-<?= $val ?>"><?= htmlspecialchars($scaleLabel) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="aptitudePrevBtn">Previous question</button>
                        </div>
                    </div>

                    <div class="card mb-3 fit-step d-none" data-step="4">
                        <div class="card-body">
                            <h5 class="card-title">4. A few practical questions</h5>
                            <p class="text-muted small">You've just told us what you're drawn to and what you're good at. Now let's check that against real life &mdash; there's no "wrong" answer here, this just keeps the result honest about access, not only interest.</p>
                            <?php foreach ($constraintFields as $key => $field): ?>
                                <div class="mb-4">
                                    <p class="mb-1 fw-semibold"><?= htmlspecialchars($field['question']) ?></p>
                                    <?php if (!empty($field['why'])): ?><p class="text-muted small mb-2"><?= htmlspecialchars($field['why']) ?></p><?php endif; ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($field['options'] as $val => $label): ?>
                                            <input type="radio" class="btn-check" name="constraints[<?= $key ?>]" id="con-<?= $key ?>-<?= $val ?>" value="<?= $val ?>" autocomplete="off" <?= ($old['constraints'][$key] ?? '') === $val ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-primary" for="con-<?= $key ?>-<?= $val ?>"><?= htmlspecialchars($label) ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-grid d-sm-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="fitPrevBtn">Back</button>
                        <button type="button" class="btn btn-primary" id="fitNextBtn">Next</button>
                        <button type="button" class="btn btn-success d-none" id="fitSubmitBtn">See my results</button>
                    </div>
                </form>

            <?php elseif ($occupation): ?>
                <?php // ============================== OCCUPATION DETAIL ============================== ?>

                <div class="mb-3"><a href="occupation.php" class="text-decoration-none">&larr; Back to careers</a></div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <span class="badge text-bg-light border mb-2"><?= htmlspecialchars($occupation['field']) ?></span>
                                <h2 class="mb-1"><?= htmlspecialchars($occupation['title']) ?></h2>
                                <?php if (!empty($occupation['alt_titles'])): ?><p class="text-muted small mb-0">Also known as: <?= htmlspecialchars(implode(', ', $occupation['alt_titles'])) ?></p><?php endif; ?>
                            </div>
                            <div class="text-end">
                                <span class="badge text-bg-<?= $occupation['demand'] === 'high' ? 'success' : ($occupation['demand'] === 'medium' ? 'warning' : 'secondary') ?> mb-1"><?= ucfirst($occupation['demand']) ?> demand</span>
                                <div class="small text-muted"><?= htmlspecialchars($occupation['salary_band']) ?></div>
                            </div>
                        </div>
                        <p class="mb-2"><?= htmlspecialchars($occupation['description']) ?></p>
                        <?php foreach ($occupation['tags'] as $tag): ?><span class="badge text-bg-light border me-1"><?= htmlspecialchars(str_replace('_', ' ', $tag)) ?></span><?php endforeach; ?>
                    </div>
                </div>

                <?php if ($savedFit): ?>
                <div class="card mb-3 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <h5 class="mb-0">Your Job Fit for this role</h5>
                            <span class="badge text-bg-<?= jf_score_class((int)$savedFit['overall']) ?> fs-6"><?= jf_verdict_label((int)$savedFit['overall']) ?></span>
                        </div>
                        <?php if (!empty($savedFit['flags'])): ?>
                            <ul class="mb-3 ps-3">
                                <?php foreach (array_slice((array)$savedFit['flags'], 0, 3) as $flag): ?><li class="mb-1"><?= htmlspecialchars($flag) ?></li><?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-3">No major mismatches based on what you told us.</p>
                        <?php endif; ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="occupation.php?id=<?= urlencode($occupation['id']) ?>&amp;fit=1" class="btn btn-primary btn-sm">See the full result</a>
                            <a href="occupation.php?id=<?= urlencode($occupation['id']) ?>&amp;fit=1&amp;redo=1" class="btn btn-outline-secondary btn-sm">Redo</a>
                        </div>
                        <p class="text-muted small mb-0 mt-2">Saved <?= date('j M Y', (int)($savedFit['completed_at'] ?? time())) ?>.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-3 border-primary">
                    <div class="card-body text-center py-4">
                        <h5 class="mb-2">Not sure you'd fit this role day to day?</h5>
                        <p class="text-muted mb-3">The Job Fit check compares what this job actually demands against what you can realistically do and sustain &mdash; not just what sounds interesting.</p>
                        <a href="occupation.php?id=<?= urlencode($occupation['id']) ?>&amp;fit=1" class="btn btn-primary btn-lg">Am I a fit for this? &rarr;</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card mb-3">
                    <div class="card-header">Subject requirements</div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush mb-3">
                            <?php foreach ($occupation['subject_requirements'] as $req): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($req['subject']) ?><?php if ($req['min_percent']): ?> <span class="text-muted small">(min <?= (int)$req['min_percent'] ?>%)</span><?php endif; ?></span>
                                    <span class="badge text-bg-<?= $req['necessity'] === 'required' ? 'primary' : 'light border' ?>"><?= ucfirst($req['necessity']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="text-muted small mb-0">Maths track: <strong><?= htmlspecialchars(str_replace('_', ' ', ucfirst($occupation['math_track']))) ?></strong><?php if ($occupation['min_aps']): ?> &middot; Typical minimum APS: <strong><?= (int)$occupation['min_aps'] ?></strong><?php endif; ?></p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Where this can lead</div>
                    <div class="card-body">
                        <?php foreach (kp_qualifications_for_occupation($occupation['id']) as $q): ?>
                            <div class="mb-3">
                                <h6 class="mb-1"><?= htmlspecialchars($q['title']) ?> <span class="text-muted small fw-normal">NQF <?= (int)$q['nqf_level'] ?> &middot; <?= htmlspecialchars($q['duration']) ?></span></h6>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach (kp_providers_for_qualification($q['id']) as $p): ?><span class="badge text-bg-light border"><?= htmlspecialchars($p['name']) ?></span><?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!empty($occupation['pathways'])): ?>
                            <h6 class="mt-3">Alternate routes in</h6>
                            <ul class="mb-0">
                                <?php foreach ($occupation['pathways'] as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <?php // ============================== DIRECTORY ============================== ?>

                <div class="mb-4">
                    <h2>Occupations</h2>
                    <p class="text-muted">Browse the careers, then check "Am I a fit for this?" on any occupation &mdash; or run a broad check across all of them at once.</p>
                </div>

                <?php if ($id !== '' && !$occupation): ?>
                    <div class="alert alert-warning">We couldn't find that occupation. Here's the full list instead.</div>
                <?php endif; ?>

                <?php if ($suggested): ?>
                <div class="card mb-4">
                    <div class="card-header">Suggested for you</div>
                    <div class="card-body pb-0">
                        <p class="text-muted small mb-0">Careers you picked or that Career Choice pointed to. Check how well each one fits you.</p>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($suggested as $sid => $source): $sf = $profile['job_fit'][$sid] ?? null; ?>
                            <a href="occupation.php?id=<?= urlencode($sid) ?>&amp;fit=1" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                                <span><?= htmlspecialchars($occupations[$sid]['title']) ?> <small class="text-muted"><?= $source ?></small></span>
                                <?php if ($sf): ?>
                                    <span class="badge text-bg-<?= jf_score_class((int)$sf['overall']) ?>"><?= jf_verdict_label((int)$sf['overall']) ?></span>
                                <?php else: ?>
                                    <span class="small text-primary text-nowrap">Check my fit &rarr;</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card mb-4 border-primary">
                    <div class="card-body text-center py-4">
                        <h5 class="mb-2">Not sure which one to check?</h5>
                        <p class="text-muted mb-3">Run the Job Fit check on its own &mdash; we'll rank every occupation in the list against your answers.</p>
                        <a href="occupation.php?fit=1" class="btn btn-outline-primary">Run a broad Job Fit check &rarr;</a>
                    </div>
                </div>

                <input type="text" class="form-control mb-3" id="occSearch" placeholder="Search occupations&hellip;">
                <div class="row g-3" id="occGrid">
                    <?php foreach ($occupations as $key => $occ): ?>
                        <div class="col-sm-6 col-lg-4 occ-item" data-search="<?= htmlspecialchars(mb_strtolower($occ['title'] . ' ' . implode(' ', $occ['alt_titles']) . ' ' . $occ['field'])) ?>">
                            <a href="occupation.php?id=<?= urlencode($key) ?>" class="card text-decoration-none h-100 text-body">
                                <div class="card-body">
                                    <span class="badge text-bg-light border mb-2"><?= htmlspecialchars($occ['field']) ?></span>
                                    <h5 class="mb-1"><?= htmlspecialchars($occ['title']) ?></h5>
                                    <p class="text-muted small mb-2"><?= htmlspecialchars($occ['salary_band']) ?></p>
                                    <span class="badge text-bg-<?= $occ['demand'] === 'high' ? 'success' : ($occ['demand'] === 'medium' ? 'warning' : 'secondary') ?>"><?= ucfirst($occ['demand']) ?> demand</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mt-2 d-none" id="occNoResults">No occupations match your search.</p>

                <div class="d-flex justify-content-between align-items-center mt-3" id="occPager">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="occPrevBtn">&larr; Previous 6</button>
                    <span class="text-muted small" id="occPageLabel"></span>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="occNextBtn">Next 6 &rarr;</button>
                </div>

            <?php endif; ?>
        </div>

        <?php if (!$results && !$showFit): ?>
        <script>
        (function(){
            var search = document.getElementById('occSearch');
            if (!search) return;
            var items = Array.prototype.slice.call(document.querySelectorAll('#occGrid .occ-item'));
            var noResults = document.getElementById('occNoResults');
            var pager = document.getElementById('occPager');
            var pageLabel = document.getElementById('occPageLabel');
            var prevBtn = document.getElementById('occPrevBtn');
            var nextBtn = document.getElementById('occNextBtn');
            var pageSize = 6;
            var page = 1;

            // Directory shows 6 at a time with Prev/Next paging through the
            // full list. Searching narrows the list instead of paging it —
            // simpler than paging a filtered set, and the search box is
            // already the "find one fast" path once there's a query.
            function render() {
                var q = search.value.trim().toLowerCase();
                var matches = items.filter(function(item){ return q === '' || item.dataset.search.indexOf(q) !== -1; });

                if (q !== '') {
                    items.forEach(function(item){ item.classList.toggle('d-none', matches.indexOf(item) === -1); });
                    pager.classList.add('d-none');
                    noResults.classList.toggle('d-none', matches.length > 0);
                    return;
                }

                noResults.classList.add('d-none');
                var totalPages = Math.max(1, Math.ceil(matches.length / pageSize));
                if (page > totalPages) page = totalPages;
                var start = (page - 1) * pageSize;
                var end = start + pageSize;
                items.forEach(function(item, i){ item.classList.toggle('d-none', i < start || i >= end); });

                pager.classList.toggle('d-none', totalPages <= 1);
                pageLabel.textContent = 'Page ' + page + ' of ' + totalPages;
                prevBtn.disabled = page <= 1;
                nextBtn.disabled = page >= totalPages;
            }

            search.addEventListener('input', function(){ page = 1; render(); });
            prevBtn.addEventListener('click', function(){ page--; render(); });
            nextBtn.addEventListener('click', function(){ page++; render(); });
            render();
        })();
        </script>
        <?php endif; ?>

        <?php if ($showFit && !$results): ?>
        <script>
        (function(){
            var steps = Array.prototype.slice.call(document.querySelectorAll('.fit-step'));
            if (!steps.length) return;
            var total = steps.length;
            var current = 1;
            var progressBar = document.getElementById('fitProgressBar');
            var badges = Array.prototype.slice.call(document.querySelectorAll('#fitStepBadges [data-badge]'));
            var prevBtn = document.getElementById('fitPrevBtn');
            var nextBtn = document.getElementById('fitNextBtn');
            var submitBtn = document.getElementById('fitSubmitBtn');
            var form = document.getElementById('fitForm');

            function show(n) {
                steps.forEach(function(s){ s.classList.toggle('d-none', +s.dataset.step !== n); });
                progressBar.style.width = (n / total * 100) + '%';
                badges.forEach(function(b){
                    var step = +b.dataset.badge;
                    b.classList.toggle('text-bg-primary', step <= n);
                    b.classList.toggle('text-bg-light', step > n);
                    b.classList.toggle('border', step > n);
                });
                prevBtn.disabled = n === 1;
                nextBtn.classList.toggle('d-none', n === total);
                submitBtn.classList.toggle('d-none', n !== total);
                current = n;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            nextBtn.addEventListener('click', function(){ show(Math.min(current + 1, total)); });
            prevBtn.addEventListener('click', function(){ show(Math.max(current - 1, 1)); });
            show(current);

            // Steps 1-3: one question at a time, career-quiz.php style —
            // tapping an answer immediately advances to the next question
            // (native radio+label btn-check, no custom click handling) and
            // the sub-quiz auto-advances into the next outer step once its
            // last question is answered. Reused for all three via config,
            // since values (6 items), context (4) and aptitude (4) all
            // share the same 3-point No/Maybe/Yes scale.
            function initSubQuiz(config) {
                var panels = Array.prototype.slice.call(document.querySelectorAll(config.itemSelector));
                if (!panels.length) return;
                var total = panels.length;
                var counter = document.getElementById(config.counterId);
                var progress = document.getElementById(config.progressId);
                var prevBtn = document.getElementById(config.prevBtnId);
                var current = 1;

                // Resume at the first unanswered question — matters after a
                // validation-error reload where earlier answers already posted.
                for (var i = 0; i < panels.length; i++) {
                    if (!panels[i].querySelector('input:checked')) { current = i + 1; break; }
                    current = i + 1;
                }
                if (current > total) current = total;

                function showPanel(n) {
                    panels.forEach(function(p){ p.classList.toggle('d-none', +p.dataset.index !== n); });
                    counter.textContent = 'Question ' + n + ' of ' + total;
                    progress.style.width = Math.round((n / total) * 100) + '%';
                    prevBtn.disabled = n === 1;
                    current = n;
                }

                panels.forEach(function(panel){
                    panel.querySelectorAll('input[type=radio]').forEach(function(radio){
                        radio.addEventListener('change', function(){
                            if (current < total) { showPanel(current + 1); }
                            else if (config.onComplete) { config.onComplete(); }
                        });
                    });
                });

                prevBtn.addEventListener('click', function(){ if (current > 1) showPanel(current - 1); });

                showPanel(current);
            }

            initSubQuiz({
                itemSelector: '.value-q', counterId: 'valueCounter', progressId: 'valueProgress', prevBtnId: 'valuePrevBtn',
                onComplete: function(){ show(2); },
            });
            initSubQuiz({
                itemSelector: '.context-q', counterId: 'contextCounter', progressId: 'contextProgress', prevBtnId: 'contextPrevBtn',
                onComplete: function(){ show(3); },
            });
            initSubQuiz({
                itemSelector: '.aptitude-q', counterId: 'aptitudeCounter', progressId: 'aptitudeProgress', prevBtnId: 'aptitudePrevBtn',
                onComplete: function(){ show(4); },
            });

            // Which step (1-4) is the first with something unanswered, or
            // null if everything's answered.
            function firstIncompleteStep() {
                var groups = {};
                form.querySelectorAll('input[type=radio]').forEach(function(r){
                    if (!(r.name in groups)) groups[r.name] = false;
                    if (r.checked) groups[r.name] = true;
                });
                for (var name in groups) {
                    if (!groups[name]) {
                        if (name.indexOf('value_rating[') === 0) return 1;
                        if (name.indexOf('context[') === 0) return 2;
                        if (name.indexOf('aptitude[') === 0) return 3;
                        if (name.indexOf('constraints[') === 0) return 4;
                    }
                }
                return null;
            }

            submitBtn.addEventListener('click', function(){
                var badStep = firstIncompleteStep();
                if (badStep) { show(badStep); return; }
                form.submit();
            });
        })();
        </script>
        <?php endif; ?>

        <?php if ($results && $results['mode'] === 'broad'): ?>
        <script>
        (function(){
            var modalEl = document.getElementById('fitModal');
            if (!modalEl) return;
            var matches = JSON.parse(document.getElementById('fitMatchData').textContent);
            var byId = {};
            matches.forEach(function(m){ byId[m.occupation_id] = m; });

            function scoreClass(score) { return score >= 75 ? 'success' : (score >= 45 ? 'warning' : 'danger'); }

            modalEl.addEventListener('show.bs.modal', function(event){
                var d = byId[event.relatedTarget.dataset.occupation];
                if (!d) return;

                document.getElementById('fitModalLabel').textContent = d.title;
                document.getElementById('fitModalField').textContent = d.field;
                var scoreEl = document.getElementById('fitModalScore');
                scoreEl.textContent = d.overall >= 75 ? 'Strong fit' : (d.overall >= 45 ? 'Partial fit' : 'Weak fit');
                scoreEl.className = 'badge text-bg-' + scoreClass(d.overall);

                var html = '<h6 class="mb-2">Worth weighing</h6>';
                html += d.flags.length
                    ? '<ul class="mb-0 ps-3">' + d.flags.map(function(f){ return '<li class="mb-1">' + f + '</li>'; }).join('') + '</ul>'
                    : '<p class="text-muted mb-0">No major mismatches based on your answers.</p>';

                document.getElementById('fitModalBody').innerHTML = html;
            });
        })();
        </script>
        <?php endif; ?>
    </body>
</html>
