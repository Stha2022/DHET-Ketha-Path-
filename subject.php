<?php
session_start();
require_once __DIR__ . '/subject-data.php';
require_once __DIR__ . '/assessment-relevance-data.php';

$grades      = sj_grades();
$languages   = sj_languages();
$mathsTracks = sj_maths_tracks();
$electives   = sj_caps_electives();
$occupations = sj_occupations();
$marksBands  = sj_marks_bands();

$results = null;
$errors  = [];
$old     = $_POST ?? [];

// TODO(db): once a database is connected, a returning Grade 11 learner
// already has subjects (and often marks) on record from Grade 10 — pull
// those and pre-fill the wizard instead of making them re-enter
// everything from scratch. Left commented out until then; the manual
// flow below is what actually runs for every grade right now.
//
// if ($_SERVER['REQUEST_METHOD'] !== 'POST' && ($_SESSION['user']['grade'] ?? '') === 'Grade 11') {
//     $stmt = $pdo->prepare(
//         'SELECT subjects, marks FROM assessments WHERE user_id = ? ORDER BY id DESC LIMIT 1'
//     );
//     $stmt->execute([$_SESSION['user']['id']]);
//     $existing = $stmt->fetch();
//     if ($existing) {
//         $old['grade']   = 'Grade 11';
//         $old['offered'] = json_decode($existing['subjects'], true) ?: [];
//         $old['marks']   = json_decode($existing['marks'], true) ?: [];
//     }
// }

/**
 * Builds the full results array from a set of wizard inputs. Shared by
 * a fresh wizard submission and by the Grade 11+ swap explorer, which
 * re-renders the same results using the base state saved in session.
 */
function sj_build_results(string $grade, string $hl, string $fal, string $maths, array $selectedSubjects, array $marks, array $intended): array {
    $occupations = sj_occupations();
    $mathsCloses = sj_maths_track_closes($maths);

    $intended = array_values(array_intersect($intended, array_keys($occupations)));
    $forward  = sj_forward_package($intended, $maths, $hl, $fal);
    $reverse  = sj_reverse_scan($selectedSubjects, $marks, $maths);

    // Only surface alternative pathways for careers the learner actually
    // said they want, not every closed occupation in the full directory.
    $closedWithPathways = array_filter($reverse, fn($r) => $r['bucket'] === 'closed' && in_array($r['key'], $intended, true));

    return [
        'unsure' => false,
        'grade' => $grade, 'gradeNote' => sj_grades()[$grade] ?? '',
        'hl' => $hl, 'fal' => $fal, 'maths' => $maths,
        'intendedLabels' => array_map(fn($k) => $occupations[$k]['label'], $intended),
        'selectedSubjects' => $selectedSubjects,
        'marks' => $marks,
        'forward' => $forward,
        'reverse' => $reverse,
        'mathsCloses' => $mathsCloses,
        'closedWithPathways' => $closedWithPathways,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'swap') {
    // Grade 11+ swap explorer: re-render the last wizard result from
    // session, plus a comparison for the subject swap they just asked about.
    $base = $_SESSION['subject_tool'] ?? null;
    if ($base) {
        $swapFrom = $_POST['swap_from'] ?? '';
        $swapTo   = $_POST['swap_to'] ?? '';
        $old = ['swap_from' => $swapFrom, 'swap_to' => $swapTo];

        if (!in_array($swapFrom, $base['selectedSubjects'], true)) {
            $errors['swap'] = 'Choose one of your current subjects to reconsider.';
        } else {
            $results = sj_build_results($base['grade'], $base['hl'], $base['fal'], $base['maths'], $base['selectedSubjects'], $base['marks'], $base['intended']);
            $results['swap'] = sj_swap_effect($base['selectedSubjects'], $base['marks'], $base['maths'], $swapFrom, $swapTo)
                + ['from' => $swapFrom, 'to' => $swapTo];
        }
    } else {
        $errors['swap'] = 'Start the wizard below first, then come back to try a swap.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade  = $_POST['grade'] ?? '';
    $hl     = $_POST['hl'] ?? '';
    $fal    = $_POST['fal'] ?? '';
    $maths  = $_POST['maths_track'] ?? '';
    $offered = $_POST['offered'] ?? [];
    $marksRaw = $_POST['marks'] ?? [];
    $intended = $_POST['intended'] ?? [];
    $unsure   = isset($_POST['unsure']);

    if (!array_key_exists($grade, $grades))            $errors['grade'] = 'Choose a grade.';
    if (!in_array($hl, $languages, true))               $errors['hl'] = 'Choose a Home Language.';
    if (!in_array($fal, $languages, true))               $errors['fal'] = 'Choose a First Additional Language.';
    if ($hl !== '' && $hl === $fal)                     $errors['fal'] = 'First Additional Language must differ from Home Language.';
    if (!in_array($maths, $mathsTracks, true))           $errors['maths_track'] = 'Choose a Maths track.';
    if (!$unsure && (empty($intended) || count($intended) > 3)) $errors['intended'] = 'Pick 1–3 intended careers, or tick "I don\'t know yet".';

    if (empty($errors)) {
        // "offered" here actually means "subjects the learner ticked as
        // currently taking" — used only for the reverse/diagnostic scan
        // below, never to constrain the forward package (see subject-data.php).
        // Ticking a subject is what makes it "selected" — the mark on top
        // of that is optional and must never be required for the subject
        // itself to count (a ticked subject with no mark still counts).
        $selectedSubjects = array_values(array_intersect($offered, $electives));
        $marks = [];
        foreach ($marksRaw as $subject => $band) {
            if ($band !== '' && isset($marksBands[$band]) && in_array($subject, $selectedSubjects, true)) {
                $marks[$subject] = $marksBands[$band];
            }
        }

        if ($unsure) {
            $results = [
                'unsure' => true, 'mathsCloses' => sj_maths_track_closes($maths),
                'grade' => $grade, 'hl' => $hl, 'fal' => $fal, 'maths' => $maths,
                'selectedSubjects' => $selectedSubjects, 'marks' => $marks,
            ];
            unset($_SESSION['subject_tool']);
        } else {
            $intendedKeys = array_values(array_intersect($intended, array_keys($occupations)));
            $results = sj_build_results($grade, $hl, $fal, $maths, $selectedSubjects, $marks, $intendedKeys);

            // Saved so the Grade 11+ swap explorer can re-run this same
            // picture without asking the learner to redo the whole wizard.
            $_SESSION['subject_tool'] = [
                'grade' => $grade, 'hl' => $hl, 'fal' => $fal, 'maths' => $maths,
                'selectedSubjects' => $selectedSubjects, 'marks' => $marks, 'intended' => $intendedKeys,
            ];
        }
    }
}

// Diagnostic mode (Gr 11-12 / Post-school): subjects are fixed, so the
// swap tool is pointless — Alternative pathways becomes the main way
// forward instead of a footer link.
$mode = $results ? ar_subject_chooser_mode($results['grade']) : null;

// Gr 9/10 without a Career Choice result yet: offer (skippable) to do
// that first, since Subject Chooser at these grades works backwards
// from a career target. Only relevant before the wizard is submitted.
$needsCareerFirst = !$results && ar_subject_chooser_needs_career_first($_SESSION['user']['grade'] ?? '', isset($_SESSION['career_quiz']));
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Subject Chooser</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4">
            <div class="mb-4">
                <h2>Subject Chooser</h2>
                <p class="text-muted">This isn't a personality quiz. Tell us your grade, languages, Maths track, subjects and intended careers, and we'll show you exactly which doors that opens and closes.</p>
            </div>

            <?php if ($needsCareerFirst): ?>
                <div class="card mb-4 border-primary" id="careerFirstInterstitial">
                    <div class="card-body">
                        <h5 class="card-title">Know what you're aiming for yet?</h5>
                        <p class="text-muted small mb-3">Subject Chooser works best once you have a career target to work backwards from. Career Choice takes a few minutes and helps with that — or skip it and pick subjects now.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="career-quiz.php" class="btn btn-primary btn-sm">Try Career Choice first</a>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="skipCareerFirstBtn">Skip, choose subjects now</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="subjectWizardWrap" class="<?= $needsCareerFirst ? 'd-none' : '' ?>">
            <?php if ($results): ?>

                <?php if ($results['unsure']): ?>
                    <div class="alert alert-info">
                        <h5 class="alert-heading">No problem! Let's start from your career interests instead.</h5>
                        <p class="mb-0">Since you're not sure yet, head to <a href="career-quiz.php" class="alert-link">Career Choice</a> to explore options first, then come back here once you have 1–3 careers in mind.</p>
                    </div>
                <?php endif; ?>

                <div class="card mb-3">
                    <div class="card-header">What you selected</div>
                    <div class="card-body">
                        <p class="mb-3">
                            <strong><?= htmlspecialchars($results['grade']) ?></strong>
                            <span class="text-muted">&nbsp;·&nbsp;</span><?= htmlspecialchars($results['hl']) ?> (Home) / <?= htmlspecialchars($results['fal']) ?> (Additional)
                            <span class="text-muted">&nbsp;·&nbsp;</span><?= htmlspecialchars($results['maths']) ?>
                        </p>

                        <div class="mb-<?= !empty($results['selectedSubjects']) ? '2' : '0' ?>">
                            <span class="text-muted small d-block mb-1">Aiming for</span>
                            <?php if (!empty($results['intendedLabels'])): ?>
                                <?php foreach ($results['intendedLabels'] as $label): ?>
                                    <span class="badge text-bg-primary-subtle text-primary-emphasis me-1 mb-1"><?= htmlspecialchars($label) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge text-bg-light border">Not sure yet</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($results['selectedSubjects'])): ?>
                            <div>
                                <?php if (htmlspecialchars($results['grade']) === 'Grade 9'): ?>
                                    <span class="text-muted small d-block mb-1">Subjects interested in</span>
                                <?php else: ?>
                                    <span class="text-muted small d-block mb-1">Currently taking</span>
                                <?php endif; ?>
                                
                                <?php foreach ($results['selectedSubjects'] as $s): ?>
                                    <span class="badge text-bg-light border me-1 mb-1"><?= htmlspecialchars($s) ?><?php if (isset($results['marks'][$s])): ?> (<?= (int)$results['marks'][$s] ?>%)<?php endif; ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($results['mathsCloses'])): ?>
                    <div class="card mb-3 border-warning">
                        <div class="card-header bg-warning-subtle">What your Maths track closes</div>
                        <div class="card-body">
                            <p class="card-text mb-2">Choosing a track other than <strong>Mathematics</strong> closes <?= count($results['mathsCloses']) ?> career path<?= count($results['mathsCloses']) === 1 ? '' : 's' ?> completely. No mark can change that.</p>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mathsClosesList">Show which careers</button>
                            <div class="collapse mt-2" id="mathsClosesList">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($results['mathsCloses'] as $c): ?>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><?= htmlspecialchars($c['label']) ?></span>
                                            <span class="badge text-bg-secondary"><?= htmlspecialchars($c['field']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$results['unsure']): ?>

                    <?php
                        $topCareers = array_values(array_filter($results['reverse'], fn($r) => $r['fit'] >= 0.5));
                        $topCareers = array_slice($topCareers, 0, 6);
                        $topPick = $topCareers[0] ?? null;
                        $rest = array_slice($topCareers, 1);
                        $bestFit = array_filter($rest, fn($r) => $r['fit'] >= 0.85);
                        $goodFit = array_filter($rest, fn($r) => $r['fit'] < 0.85);
                    ?>
                    <div class="card mb-3">
                        <div class="card-header">Your best career fits</div>
                        <div class="card-body">
                            <p class="text-muted small">Tap a career for more on it.</p>

                            <?php if (!$topPick): ?>
                                <p class="text-muted mb-0">None of the careers we checked reach 50% fit with your current subjects yet. Tick more subjects in step 4, or add marks, to see where you stand.</p>
                            <?php else: ?>
                                <button type="button" class="btn text-start w-100 p-3 mb-3 border border-success border-2 rounded-3 bg-success-subtle" data-bs-toggle="modal" data-bs-target="#careerModal" data-career="<?= htmlspecialchars($topPick['label']) ?>" data-field="<?= htmlspecialchars($topPick['field']) ?>" data-fit="<?= round($topPick['fit'] * 100) ?>" data-bucket="<?= htmlspecialchars($topPick['bucket']) ?>">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <span class="badge text-bg-success mb-1">Your top match</span>
                                            <h5 class="mb-0"><?= htmlspecialchars($topPick['label']) ?></h5>
                                            <span class="text-muted small"><?= htmlspecialchars($topPick['field']) ?></span>
                                        </div>
                                        <div class="fs-2 fw-bold text-success"><?= round($topPick['fit'] * 100) ?>%</div>
                                    </div>
                                </button>

                                <?php if (!empty($bestFit)): ?>
                                    <h6 class="text-muted small text-uppercase mt-3">Best fit</h6>
                                    <div class="list-group mb-3">
                                        <?php foreach ($bestFit as $r): ?>
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="modal" data-bs-target="#careerModal" data-career="<?= htmlspecialchars($r['label']) ?>" data-field="<?= htmlspecialchars($r['field']) ?>" data-fit="<?= round($r['fit'] * 100) ?>" data-bucket="<?= htmlspecialchars($r['bucket']) ?>">
                                                <span><?= htmlspecialchars($r['label']) ?> <small class="text-muted"><?= htmlspecialchars($r['field']) ?></small></span>
                                                <span class="badge text-bg-success"><?= round($r['fit'] * 100) ?>%</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($goodFit)): ?>
                                    <h6 class="text-muted small text-uppercase mt-3">Good fit</h6>
                                    <div class="list-group">
                                        <?php foreach ($goodFit as $r): ?>
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-bs-toggle="modal" data-bs-target="#careerModal" data-career="<?= htmlspecialchars($r['label']) ?>" data-field="<?= htmlspecialchars($r['field']) ?>" data-fit="<?= round($r['fit'] * 100) ?>" data-bucket="<?= htmlspecialchars($r['bucket']) ?>">
                                                <span><?= htmlspecialchars($r['label']) ?> <small class="text-muted"><?= htmlspecialchars($r['field']) ?></small></span>
                                                <span class="badge text-bg-warning"><?= round($r['fit'] * 100) ?>%</span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php
                        // Diagnostic mode (Gr 11-12 / Post-school): subjects can't
                        // change, so Alternative pathways is the main route forward
                        // and belongs up here, not buried below as a footer link.
                        ob_start();
                    ?>
                    <?php if (!empty($results['closedWithPathways'])): ?>
                    <div class="card mb-3 <?= $mode === AR_MODE_DIAGNOSTIC ? 'border-primary' : '' ?>">
                        <div class="card-header <?= $mode === AR_MODE_DIAGNOSTIC ? 'bg-primary-subtle' : '' ?>">Alternative pathways</div>
                        <p class="text-muted small px-3 pt-2 mb-0">
                            <?= $mode === AR_MODE_DIAGNOSTIC
                                ? 'Your subjects can\'t change now, so here\'s how to still reach the career path you want:'
                                : 'Your current subjects don\'t fully open the career path you want yet. Here\'s another way in:' ?>
                        </p>
                        <div class="accordion accordion-flush mt-2" id="pathwaysAccordion">
                            <?php $i = 0; foreach ($results['closedWithPathways'] as $r): $i++; ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#path<?= $i ?>">
                                            <?= htmlspecialchars($r['label']) ?>
                                        </button>
                                    </h2>
                                    <div id="path<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#pathwaysAccordion">
                                        <div class="accordion-body">
                                            <ul class="mb-0">
                                                <?php foreach ($r['pathways'] as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php
                        $pathwaysHtml = ob_get_clean();
                        if ($mode === AR_MODE_DIAGNOSTIC) echo $pathwaysHtml;
                    ?>

                    <?php if (!empty($results['intendedLabels'])): ?>
                    <div class="card mb-3 border-primary">
                        <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>Subjects for the career path you want</span>
                            <span class="badge text-bg-success">Opens <?= (int)$results['forward']['opensCount'] ?> of <?= count($results['intendedLabels']) ?> careers you picked</span>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">For <strong><?= htmlspecialchars(implode(', ', $results['intendedLabels'])) ?></strong>, these elective subjects matter most:</p>
                            <?php $missing = array_values(array_diff($results['forward']['package'], $results['selectedSubjects'])); ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($results['forward']['package'] as $c): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($c) ?>
                                        <?php if (in_array($c, $missing, true)): ?><span class="badge text-bg-warning">Missing</span><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <p class="mb-2">On top of that, every learner also takes these compulsory subjects:</p>
                            <ul class="list-group mb-3">
                                <?php foreach ($results['forward']['compulsory'] as $c): ?>
                                    <li class="list-group-item text-muted"><?= htmlspecialchars($c) ?></li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if ($results['forward']['conflict']): ?>
                                <div class="alert alert-danger">
                                    <strong>Trade-off:</strong> your chosen careers need <?= count($results['forward']['requiredElectives']) ?> required elective subjects, but CAPS only gives you 3 elective slots:
                                    <?= htmlspecialchars(implode(', ', $results['forward']['requiredElectives'])) ?>.
                                    You'll need to drop one of your intended careers, or accept that one of them stays partly closed.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($missing)): ?>
                                <?php if ($mode === AR_MODE_DIAGNOSTIC): ?>
                                    <p class="text-muted small mb-0">You're missing <strong><?= htmlspecialchars(implode(', ', $missing)) ?></strong>, but your subjects are already set — swapping isn't an option now. See <strong>Alternative pathways</strong> below for other ways in.</p>
                                <?php elseif (!empty($results['selectedSubjects'])): ?>
                                    <p class="text-muted small mb-2">You're missing <strong><?= htmlspecialchars(implode(', ', $missing)) ?></strong> from what you're currently taking.</p>
                                    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#swapModal">Swap a subject</button>
                                    <?php if (isset($errors['swap'])): ?><div class="text-danger small mt-2"><?= $errors['swap'] ?></div><?php endif; ?>

                                    <div class="modal fade" id="swapModal" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Swap a subject</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="text-muted small">See what dropping one of your current subjects for one of the missing ones above would actually change.</p>

                                                    <?php if (isset($results['swap'])): ?>
                                                        <div class="alert alert-secondary py-2">
                                                            <strong>Dropping <?= htmlspecialchars($results['swap']['from']) ?><?= $results['swap']['to'] ? ' and adding ' . htmlspecialchars($results['swap']['to']) : '' ?>:</strong>
                                                            <?php if (empty($results['swap']['opened']) && empty($results['swap']['closed'])): ?>
                                                                <p class="mb-0 mt-2">No change. Every career's status stays the same.</p>
                                                            <?php else: ?>
                                                                <?php if (!empty($results['swap']['opened'])): ?>
                                                                    <p class="mb-1 mt-2 text-success">Opens: <?= htmlspecialchars(implode(', ', array_column($results['swap']['opened'], 'label'))) ?></p>
                                                                <?php endif; ?>
                                                                <?php if (!empty($results['swap']['closed'])): ?>
                                                                    <p class="mb-0 text-danger">Closes: <?= htmlspecialchars(implode(', ', array_column($results['swap']['closed'], 'label'))) ?></p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <form method="post" action="subject.php" class="row g-2 align-items-end">
                                                        <input type="hidden" name="action" value="swap">
                                                        <div class="col-12">
                                                            <label class="form-label small mb-1" for="swap_from">Subject to drop</label>
                                                            <select class="form-select form-select-sm" name="swap_from" id="swap_from">
                                                                <?php foreach ($results['selectedSubjects'] as $s): ?>
                                                                    <option value="<?= htmlspecialchars($s) ?>" <?= (($old['swap_from'] ?? '') === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label small mb-1" for="swap_to">Swap in instead</label>
                                                            <select class="form-select form-select-sm" name="swap_to" id="swap_to">
                                                                <option value="">Just drop it</option>
                                                                <?php foreach ($missing as $m): ?>
                                                                    <option value="<?= htmlspecialchars($m) ?>" <?= (($old['swap_to'] ?? $missing[0]) === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                                                                <?php endforeach; ?>
                                                                <?php foreach ($electives as $e): if (in_array($e, $results['selectedSubjects'], true) || in_array($e, $missing, true)) continue; ?>
                                                                    <option value="<?= htmlspecialchars($e) ?>" <?= (($old['swap_to'] ?? '') === $e) ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-12">
                                                            <button type="submit" class="btn btn-warning btn-sm w-100">Compare</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small mb-0">Tick your current subjects in step 4 to see if swapping one could get you there.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="modal fade" id="careerModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title" id="careerModalLabel"></h5>
                                        <span class="badge text-bg-light border" id="careerModalField"></span>
                                        <span class="badge" id="careerModalStatus"></span>
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
                                        <h6>About this career</h6>
                                        <p id="careerModalAbout"></p>
                                        <h6>Possible trajectory</h6>
                                        <ol class="list-group list-group-numbered" id="careerModalTrajectory"></ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($mode !== AR_MODE_DIAGNOSTIC) echo $pathwaysHtml; ?>

                <?php endif; ?>

                <a href="subject.php" class="btn btn-outline-secondary">Start over</a>

            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">Please fix the highlighted fields below.</div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-2" id="stepBadges">
                    <?php $stepLabels = ['Grade', 'Languages', 'Maths', 'Subjects', 'Careers']; foreach ($stepLabels as $i => $label): ?>
                        <div class="text-center flex-fill">
                            <span class="badge rounded-pill text-bg-light border" data-badge="<?= $i + 1 ?>"><?= $i + 1 ?></span>
                            <div class="small text-muted d-none d-sm-block"><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="progress mb-4" style="height:6px" role="progressbar">
                    <div class="progress-bar" id="progressBar" style="width:20%"></div>
                </div>

                <form method="post" action="subject.php" id="subjectForm" novalidate>

                    <div class="card mb-3 step-card" data-step="1">
                        <div class="card-body">
                            <h5 class="card-title">1. What grade are you in?</h5>
                            <div class="row g-2" id="gradeGroup">
                                <?php foreach ($grades as $g => $note): ?>
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check grade-radio" name="grade" id="grade-<?= $g ?>" value="<?= $g ?>" autocomplete="off" <?= (($old['grade'] ?? '') === $g) ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary w-100 h-100 py-3" for="grade-<?= $g ?>"><?= $g ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-muted small mt-3 mb-0" id="gradeNote">&nbsp;</p>
                            <?php if (isset($errors['grade'])): ?><div class="text-danger small mt-2"><?= $errors['grade'] ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3 step-card d-none" data-step="2">
                        <div class="card-body">
                            <h5 class="card-title">2. Home Language &amp; First Additional Language</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="hl">Home Language</label>
                                    <select class="form-select" name="hl" id="hl">
                                        <option value="">Choose…</option>
                                        <?php foreach ($languages as $l): ?>
                                            <option value="<?= $l ?>" <?= (($old['hl'] ?? '') === $l) ? 'selected' : '' ?>><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['hl'])): ?><div class="text-danger small mt-1"><?= $errors['hl'] ?></div><?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="fal">First Additional Language</label>
                                    <select class="form-select" name="fal" id="fal">
                                        <option value="">Choose…</option>
                                        <?php foreach ($languages as $l): ?>
                                            <option value="<?= $l ?>" <?= (($old['fal'] ?? '') === $l) ? 'selected' : '' ?>><?= $l ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['fal'])): ?><div class="text-danger small mt-1"><?= $errors['fal'] ?></div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3 step-card d-none" data-step="3">
                        <div class="card-body">
                            <h5 class="card-title">3. Which mathematics subject are you taking?</h5>
                            <p class="text-muted small">The single highest-leverage question in South African schooling.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($mathsTracks as $m): ?>
                                    <input type="radio" class="btn-check" name="maths_track" id="mt-<?= md5($m) ?>" value="<?= $m ?>" autocomplete="off" <?= (($old['maths_track'] ?? '') === $m) ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary px-4 py-2" for="mt-<?= md5($m) ?>"><?= $m ?></label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['maths_track'])): ?><div class="text-danger small mt-2"><?= $errors['maths_track'] ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3 step-card d-none" data-step="4">
                        <div class="card-body">
                            <h5 class="card-title">4. Which subjects are you currently taking?</h5>
                            <p class="text-muted small">Search and tap to add a subject. This just checks how you stack up against different careers right now. The subjects we recommend below assume your school offers the full CAPS list, so what you tick here won't limit that.</p>
                            <input type="text" class="form-control mb-3" id="electiveSearch" placeholder="Search subjects…">
                            <div class="d-flex flex-wrap gap-2" id="electiveList">
                                <?php foreach ($electives as $e): ?>
                                    <div class="elective-item">
                                        <input type="checkbox" class="btn-check offered-check" name="offered[]" id="off-<?= md5($e) ?>" value="<?= $e ?>" autocomplete="off" <?= in_array($e, (array)($old['offered'] ?? []), true) ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-secondary btn-sm" for="off-<?= md5($e) ?>"><?= $e ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="marksSection" class="mt-4 pt-3 border-top d-none">
                                <h6>Current marks <small class="text-muted fw-normal">(optional)</small></h6>
                                <p class="text-muted small">Only shown for subjects you've ticked above. Fill in what you know.</p>
                                <p class="text-muted small fst-italic" id="noMarksYet">Tick a subject above to add a mark for it.</p>
                                <div id="marksList">
                                    <?php foreach ($electives as $e): ?>
                                        <div class="row g-2 align-items-center mb-2 mark-row d-none" data-for="off-<?= md5($e) ?>">
                                            <div class="col-7 col-md-6"><?= $e ?></div>
                                            <div class="col-5 col-md-6">
                                                <select class="form-select form-select-sm" name="marks[<?= htmlspecialchars($e) ?>]">
                                                    <option value="">No mark yet</option>
                                                    <?php foreach (array_keys($marksBands) as $band): ?>
                                                        <option value="<?= $band ?>" <?= (($old['marks'][$e] ?? '') === $band) ? 'selected' : '' ?>><?= $band ?>%</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3 step-card d-none" data-step="5">
                        <div class="card-body">
                            <h5 class="card-title">5. Intended career or field</h5>
                            <p class="text-muted small">Pick 1–3, or tell us you're not sure yet.</p>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="unsure" id="unsure" value="1" <?= !empty($old['unsure']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="unsure"><strong>I don't know yet</strong>, take me to Career Choice instead</label>
                            </div>
                            <div id="intendedGroup" class="accordion">
                                <?php
                                $byField = [];
                                foreach ($occupations as $key => $occ) { $byField[$occ['field']][] = ['key' => $key, 'label' => $occ['label']]; }
                                $fi = 0;
                                foreach ($byField as $field => $items): $fi++;
                                    $fieldHasChecked = false;
                                    foreach ($items as $it) { if (in_array($it['key'], (array)($old['intended'] ?? []), true)) $fieldHasChecked = true; }
                                ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $fieldHasChecked ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#field<?= $fi ?>">
                                                <?= htmlspecialchars($field) ?> <span class="badge text-bg-light border ms-2"><?= count($items) ?></span>
                                            </button>
                                        </h2>
                                        <div id="field<?= $fi ?>" class="accordion-collapse collapse <?= $fieldHasChecked ? 'show' : '' ?>" data-bs-parent="#intendedGroup">
                                            <div class="accordion-body d-flex flex-wrap gap-2">
                                                <?php foreach ($items as $it): ?>
                                                    <input type="checkbox" class="btn-check intended-check" name="intended[]" id="oc-<?= $it['key'] ?>" value="<?= $it['key'] ?>" autocomplete="off" <?= in_array($it['key'], (array)($old['intended'] ?? []), true) ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-primary btn-sm" for="oc-<?= $it['key'] ?>"><?= htmlspecialchars($it['label']) ?></label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['intended'])): ?><div class="text-danger small mt-2"><?= $errors['intended'] ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid d-sm-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="prevBtn">Back</button>
                        <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
                        <button type="submit" class="btn btn-success d-none" id="submitBtn">See my results</button>
                    </div>
                </form>

            <?php endif; ?>
            </div>
        </div>

        <script>
        (function(){
            var skipBtn = document.getElementById('skipCareerFirstBtn');
            if (skipBtn) {
                skipBtn.addEventListener('click', function(){
                    document.getElementById('careerFirstInterstitial').classList.add('d-none');
                    document.getElementById('subjectWizardWrap').classList.remove('d-none');
                });
            }
        })();
        </script>

        <script>
        (function(){
            var steps = Array.prototype.slice.call(document.querySelectorAll('.step-card'));
            if (!steps.length) return;
            var total = steps.length;
            var current = 1;
            var progressBar = document.getElementById('progressBar');
            var badges = Array.prototype.slice.call(document.querySelectorAll('#stepBadges [data-badge]'));
            var prevBtn = document.getElementById('prevBtn');
            var nextBtn = document.getElementById('nextBtn');
            var submitBtn = document.getElementById('submitBtn');

            function show(n) {
                steps.forEach(function(s) { s.classList.toggle('d-none', +s.dataset.step !== n); });
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

            // Grade note + only ask for marks from Grade 10 and up (Grade 9
            // learners haven't started electives yet, so marks make no sense).
            var gradeNotes = <?= json_encode($grades) ?>;
            var gradeRadios = document.querySelectorAll('.grade-radio');
            var gradeNoteEl = document.getElementById('gradeNote');
            var marksSection = document.getElementById('marksSection');
            function syncGrade() {
                var checked = document.querySelector('.grade-radio:checked');
                var grade = checked ? checked.value : '';
                gradeNoteEl.textContent = grade ? (gradeNotes[grade] || '') : ' ';
                marksSection.classList.toggle('d-none', grade === '' || grade === 'Grade 9');
            }
            gradeRadios.forEach(function(r){ r.addEventListener('change', syncGrade); });
            syncGrade();

            // Tapping a subject chip reveals (or hides) its mark row below.
            var offeredChecks = document.querySelectorAll('.offered-check');
            var noMarksYet = document.getElementById('noMarksYet');
            function syncMarkRow(check) {
                var row = document.querySelector('.mark-row[data-for="' + check.id + '"]');
                if (row) row.classList.toggle('d-none', !check.checked);
            }
            function syncNoMarksYet() {
                var anyChecked = Array.prototype.some.call(offeredChecks, function(c){ return c.checked; });
                noMarksYet.classList.toggle('d-none', anyChecked);
            }
            offeredChecks.forEach(function(c){
                syncMarkRow(c);
                c.addEventListener('change', function(){ syncMarkRow(c); syncNoMarksYet(); });
            });
            syncNoMarksYet();

            var search = document.getElementById('electiveSearch');
            if (search) {
                search.addEventListener('input', function(){
                    var q = search.value.trim().toLowerCase();
                    document.querySelectorAll('#electiveList .elective-item').forEach(function(item){
                        var label = item.textContent.trim().toLowerCase();
                        item.classList.toggle('d-none', q !== '' && label.indexOf(q) === -1);
                    });
                });
            }

            var unsure = document.getElementById('unsure');
            var intendedChecks = document.querySelectorAll('.intended-check');
            var intendedGroup = document.getElementById('intendedGroup');
            function syncUnsure() {
                var isUnsure = unsure.checked;
                intendedGroup.classList.toggle('opacity-50', isUnsure);
                intendedChecks.forEach(function(c){ c.disabled = isUnsure; if (isUnsure) c.checked = false; });
            }
            if (unsure) { unsure.addEventListener('change', syncUnsure); syncUnsure(); }

            intendedChecks.forEach(function(c){
                c.addEventListener('change', function(){
                    var checked = Array.prototype.filter.call(intendedChecks, function(x){ return x.checked; });
                    if (checked.length > 3) { c.checked = false; }
                });
            });
        })();
        </script>

        <script>
        (function(){
            // Career detail modal, mock only: no real AI call yet. Tapping a
            // row shows a fake "generating" delay then placeholder content,
            // so the shape of the real feature is there to wire up later.
            var careerModal = document.getElementById('careerModal');
            if (careerModal) {
                careerModal.addEventListener('show.bs.modal', function(event){
                    var row = event.relatedTarget;
                    var career = row.dataset.career;
                    var field = row.dataset.field;
                    var fit = row.dataset.fit;
                    var bucket = row.dataset.bucket;
                    var statusText = { open: 'Open', effort: 'Open with effort', closed: 'Closed' }[bucket] || '';
                    var statusClass = { open: 'text-bg-success', effort: 'text-bg-warning', closed: 'text-bg-secondary' }[bucket] || 'text-bg-secondary';

                    document.getElementById('careerModalLabel').textContent = career;
                    document.getElementById('careerModalField').textContent = field;
                    var statusEl = document.getElementById('careerModalStatus');
                    statusEl.textContent = statusText + ' (' + fit + '% fit)';
                    statusEl.className = 'badge ' + statusClass;

                    var loading = document.getElementById('careerModalLoading');
                    var content = document.getElementById('careerModalContent');
                    loading.classList.remove('d-none');
                    content.classList.add('d-none');

                    setTimeout(function(){
                        document.getElementById('careerModalAbout').textContent =
                            'Placeholder text. Here Khetha\'s AI will explain, in plain language, what a ' + career + ' actually does day to day in ' + field + ', and why your subjects and marks make it a ' + statusText.toLowerCase() + ' option for you.';

                        var steps = [
                            'Finish school with the right subjects for ' + career + '.',
                            'Study the relevant qualification (degree, diploma or learnership).',
                            'Get an entry-level role in ' + field + ' and build experience.',
                            'Grow into a specialist or leadership role over time.'
                        ];
                        var list = document.getElementById('careerModalTrajectory');
                        list.innerHTML = '';
                        steps.forEach(function(step){
                            var li = document.createElement('li');
                            li.className = 'list-group-item';
                            li.textContent = step;
                            list.appendChild(li);
                        });

                        loading.classList.add('d-none');
                        content.classList.remove('d-none');
                    }, 700);
                });
            }

            // Subject swap explorer, shown on the results view when
            // something's missing for the career path the learner wants —
            // lives outside the wizard IIFE above since it only exists
            // once the form has already been submitted.
            <?php if (isset($results['swap'])): ?>
            var swapModalEl = document.getElementById('swapModal');
            if (swapModalEl) new bootstrap.Modal(swapModalEl).show();
            <?php endif; ?>
        })();
        </script>
    </body>
</html>
