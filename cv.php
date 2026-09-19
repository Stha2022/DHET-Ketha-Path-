<?php
// My CV: a CV started for the learner from what Khetha already knows. They can edit any of it;
// their edits are kept separately (see includes/cv.php), and "Reset" goes back to what Khetha knows.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/cv.php';
require_once __DIR__ . '/includes/csrf.php';
kp_require_auth();

$userId = (int)($_SESSION['user']['id'] ?? 0);
$account = ['name' => $_SESSION['user']['name'] ?? '', 'email' => $_SESSION['user']['email'] ?? ''];

$db = cv_db();                                   // null in demo mode
$built = cv_build($userId, $db, $account);       // read-only: what Khetha knows
$store = $built['source'] === 'db' ? $db : null; // edits go where the CV came from

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'reset') {
        cv_reset_edits($userId, $store) ? header('Location: cv.php?reset=1') : $error = 'Could not reset your edits. Please try again.';
    } else {
        cv_save_edits($userId, $_POST, $built, $store) ? header('Location: cv.php?saved=1') : $error = 'Could not save your edits. Please try again.';
    }
    if (!$error) exit;
}

$edits = cv_get_edits($userId, $store);
$cv = cv_apply($built, $edits);
$nudges = cv_nudges($cv);
$editing = isset($_GET['edit']) || $error !== '';
$e = fn($v) => htmlspecialchars((string)$v);
$lines = fn(array $a) => htmlspecialchars(implode("\n", $a));
$resultLines = array_map(fn($r) => $r['subject'] . ': ' . $r['value'], $cv['results']);
$isEdited = fn($k) => in_array($k, $cv['edited'], true);
$s = $cv['school'];
$rec = $cv['recommendations'];
$hasSchool = $s['grade'] !== '' || $s['school_name'] !== '' || $s['home_language'] !== '' || $s['maths_track'] !== '' || $s['notes'] !== '';
$hasReco = $rec['career_choice'] || $rec['job_fit'] || $rec['subject_chooser'];
$shownNudges = array_slice($nudges['missing'], 0, 4);
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <title>My CV — Khetha</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .cv-sheet{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px 26px;box-shadow:0 10px 30px rgba(16,42,67,.05)}
            .cv-name{font-size:30px;font-weight:800;line-height:1.1;margin:0 0 4px}
            .cv-contact{color:var(--muted);font-size:14px;margin:0 0 6px}
            .cv-sec{margin-top:22px;break-inside:avoid}
            .cv-sec h2{font-size:12px;letter-spacing:1.3px;text-transform:uppercase;color:var(--teal);font-weight:800;border-bottom:1px solid var(--line);padding-bottom:6px;margin:0 0 10px}
            .cv-sec p,.cv-sec li{font-size:14px;line-height:1.55}
            .cv-sec ul{padding-left:18px;margin:0}
            .cv-tags{display:flex;flex-wrap:wrap;gap:6px;padding:0;margin:0;list-style:none}
            .cv-tags li{border:1px solid var(--line);border-radius:999px;padding:3px 12px;font-size:13px}
            .cv-edited{font-size:10px;font-weight:700;color:#8a6d00;background:#fff4cc;border-radius:999px;padding:1px 8px;margin-left:8px;letter-spacing:0;text-transform:none;vertical-align:middle}
            .cv-empty{color:var(--muted);font-style:italic}
            .cv-sec-empty::after{content:"Not added yet.";color:var(--muted);font-style:italic;font-size:13px}
            .cv-form label{font-weight:700;font-size:13px;margin-bottom:4px}
            .cv-form .hint{font-size:12px;color:var(--muted);margin:4px 0 0}
            @media print{
                /* nav.navbar.kp-navbar out-ranks Bootstrap's .d-flex!important, which loads after this sheet */
                nav.navbar.kp-navbar,.no-print,.dropdown-menu{display:none!important}
                body{background:#fff!important}
                .cv-wrap{max-width:none!important;padding:0!important}
                .cv-sheet{border:0;box-shadow:none;padding:0}
                .cv-sec-empty{display:none!important}
                .cv-edited{display:none}
                /* Tighter on paper than on screen, so a full CV still lands on one page. */
                .cv-name{font-size:24px}
                .cv-sec{margin-top:14px}
                .cv-sec h2{padding-bottom:3px;margin-bottom:6px}
                .cv-sec p,.cv-sec li{font-size:13px;line-height:1.4}
                .cv-sec li{margin-bottom:2px}
                .cv-tags li{padding:1px 9px;font-size:12px}
                @page{margin:14mm}
            }
        </style>
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4 cv-wrap" style="max-width:820px">

            <div class="no-print">
                <h2 class="mb-1">My CV</h2>
                <p class="text-muted">We started this for you from what you've already told Khetha. Edit anything that's wrong or missing, then print it or save it as a PDF.</p>

                <?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Saved. Your edits are kept separately, so anything you didn't change keeps following your profile.</div><?php endif; ?>
                <?php if (isset($_GET['reset'])): ?><div class="alert alert-info py-2">Back to what Khetha knows. Your edits were removed.</div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= $e($error) ?></div><?php endif; ?>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-baseline mb-2">
                            <h6 class="mb-0">How complete is your CV?</h6>
                            <span class="fw-bold" id="cvPercent"><?= (int)$nudges['percent'] ?>%</span>
                        </div>
                        <div class="progress mb-2" style="height:8px" role="progressbar" aria-valuenow="<?= (int)$nudges['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:<?= (int)$nudges['percent'] ?>%"></div>
                        </div>
                        <?php if ($shownNudges): ?>
                            <ul class="list-unstyled small mb-0 mt-2" id="cvNudges">
                                <?php foreach ($shownNudges as $n): ?>
                                    <li class="mb-1"><a href="<?= $e($n['url']) ?>" class="text-decoration-none">+ <?= $e($n['label']) ?></a></li>
                                <?php endforeach; ?>
                                <?php if (count($nudges['missing']) > count($shownNudges)): ?><li class="text-muted">and <?= count($nudges['missing']) - count($shownNudges) ?> more</li><?php endif; ?>
                            </ul>
                        <?php else: ?>
                            <p class="small text-success mb-0" id="cvNudges">Nothing missing. Your CV is ready to print.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$editing): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="cv.php?edit=1" class="btn btn-primary btn-sm">Edit my CV</a>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">Print / Save as PDF</button>
                    <?php if ($cv['edited']): ?>
                        <form method="post" action="cv.php" class="d-inline" onsubmit="return confirm('Remove all your edits and go back to what Khetha knows?')">
                            <input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="reset">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Reset to what Khetha knows</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($editing): ?>
            <form method="post" action="cv.php" class="cv-form card no-print">
                <input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>">
                <div class="card-body">
                    <h5 class="mb-3">Edit my CV</h5>

                    <div class="mb-3">
                        <label for="summary">Profile summary <?php if ($isEdited('summary')): ?><span class="cv-edited">edited</span><?php endif; ?></label>
                        <textarea class="form-control" id="summary" name="summary" rows="5" maxlength="1500"><?= $e($cv['summary']) ?></textarea>
                        <p class="hint">Drafted from your profile. Rewrite it in your own words.</p>
                    </div>

                    <div class="mb-3" id="contact">
                        <label for="contact_name">Name</label>
                        <input class="form-control mb-2" id="contact_name" name="contact_name" maxlength="200" value="<?= $e($cv['contact']['name']) ?>">
                        <label for="contact_email">Email</label>
                        <input class="form-control mb-2" type="email" id="contact_email" name="contact_email" maxlength="200" value="<?= $e($cv['contact']['email']) ?>">
                        <label for="contact_phone">Phone number</label>
                        <input class="form-control" type="tel" id="contact_phone" name="contact_phone" maxlength="200" value="<?= $e($cv['contact']['phone']) ?>" placeholder="e.g. 082 000 0000">
                    </div>

                    <div class="mb-3" id="school">
                        <label for="school_name">School</label>
                        <input class="form-control mb-2" id="school_name" name="school_name" maxlength="200" value="<?= $e($s['school_name']) ?>" placeholder="e.g. Khayelitsha High School">
                        <label for="school_grade">Grade</label>
                        <input class="form-control mb-2" id="school_grade" name="school_grade" maxlength="200" value="<?= $e($s['grade']) ?>">
                        <label for="school_notes">About your school journey</label>
                        <textarea class="form-control" id="school_notes" name="school_notes" rows="2" maxlength="1500" placeholder="e.g. Class captain, member of the science club"><?= $e($s['notes']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="subjects">Subjects <?php if ($isEdited('subjects')): ?><span class="cv-edited">edited</span><?php endif; ?></label>
                        <textarea class="form-control" id="subjects" name="subjects" rows="4"><?= $lines($cv['subjects']) ?></textarea>
                        <p class="hint">One per line.</p>
                    </div>

                    <div class="mb-3">
                        <label for="results">Checked results <?php if ($isEdited('results')): ?><span class="cv-edited">edited</span><?php endif; ?></label>
                        <textarea class="form-control" id="results" name="results" rows="4"><?= $lines($resultLines) ?></textarea>
                        <p class="hint">One per line, like <em>Physical Sciences: 72%</em> or <em>Physical Sciences: 60-69%</em>.</p>
                    </div>

                    <div class="mb-3">
                        <label for="interests">Interests <?php if ($isEdited('interests')): ?><span class="cv-edited">edited</span><?php endif; ?></label>
                        <textarea class="form-control" id="interests" name="interests" rows="3"><?= $lines($cv['interests']) ?></textarea>
                        <p class="hint">One per line. To change the chips Khetha uses to rank careers, use <a href="my-profile.php#interests">My Profile</a>.</p>
                    </div>

                    <div class="mb-3">
                        <label for="pathway_extra">More pathway ideas</label>
                        <textarea class="form-control" id="pathway_extra" name="pathway_extra" rows="3"><?= $lines($cv['pathway_extra']) ?></textarea>
                        <p class="hint">Careers or courses you're thinking about, one per line. They're added to the ones Khetha suggests.</p>
                    </div>

                    <div class="mb-3" id="achievements">
                        <label for="achievements_field">Achievements &amp; activities</label>
                        <textarea class="form-control" id="achievements_field" name="achievements" rows="4"><?= $lines($cv['achievements']) ?></textarea>
                        <p class="hint">Awards, sport, clubs, volunteering, jobs. One per line.</p>
                    </div>

                    <div class="d-grid d-sm-flex gap-2">
                        <a href="cv.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save my CV</button>
                    </div>
                </div>
            </form>

            <?php else: ?>
            <article class="cv-sheet" id="cvSheet">
                <h1 class="cv-name"><?= $e($cv['contact']['name'] !== '' ? $cv['contact']['name'] : 'Your name') ?></h1>
                <p class="cv-contact">
                    <?= implode(' &nbsp;&bull;&nbsp; ', array_filter([$e($cv['contact']['email']), $e($cv['contact']['phone'])])) ?: '<span class="cv-empty">Add an email or phone number</span>' ?>
                </p>

                <section class="cv-sec <?= $cv['summary'] === '' ? 'cv-sec-empty' : '' ?>" id="cv-summary">
                    <h2>Profile summary <?php if ($isEdited('summary')): ?><span class="cv-edited">edited</span><?php endif; ?></h2>
                    <p class="mb-0"><?= nl2br($e($cv['summary'])) ?></p>
                </section>

                <section class="cv-sec <?= $hasSchool ? '' : 'cv-sec-empty' ?>" id="cv-school">
                    <h2>School journey</h2>
                    <ul>
                        <?php if ($s['school_name'] !== ''): ?><li><strong>School:</strong> <?= $e($s['school_name']) ?></li><?php endif; ?>
                        <?php if ($s['grade'] !== ''): ?><li><strong>Current level:</strong> <?= $e($s['grade']) ?></li><?php endif; ?>
                        <?php if ($s['home_language'] !== ''): ?><li><strong>Languages:</strong> <?= $e($s['home_language']) ?> (Home)<?= $s['fal'] !== '' ? ', ' . $e($s['fal']) . ' (First Additional)' : '' ?></li><?php endif; ?>
                        <?php if ($s['maths_track'] !== ''): ?><li><strong>Maths subject:</strong> <?= $e($s['maths_track']) ?></li><?php endif; ?>
                        <?php if ($s['notes'] !== ''): ?><li><?= nl2br($e($s['notes'])) ?></li><?php endif; ?>
                    </ul>
                </section>

                <section class="cv-sec <?= $cv['subjects'] ? '' : 'cv-sec-empty' ?>" id="cv-subjects">
                    <h2>Subjects <?php if ($isEdited('subjects')): ?><span class="cv-edited">edited</span><?php endif; ?></h2>
                    <ul class="cv-tags"><?php foreach ($cv['subjects'] as $x): ?><li><?= $e($x) ?></li><?php endforeach; ?></ul>
                </section>

                <section class="cv-sec <?= $cv['results'] ? '' : 'cv-sec-empty' ?>" id="cv-results">
                    <h2>Checked results <?php if ($isEdited('results')): ?><span class="cv-edited">edited</span><?php endif; ?></h2>
                    <ul><?php foreach ($cv['results'] as $r): ?><li><?= $e($r['subject']) ?>: <strong><?= $e($r['value']) ?></strong></li><?php endforeach; ?></ul>
                </section>

                <section class="cv-sec <?= $cv['interests'] ? '' : 'cv-sec-empty' ?>" id="cv-interests">
                    <h2>Interests <?php if ($isEdited('interests')): ?><span class="cv-edited">edited</span><?php endif; ?></h2>
                    <ul class="cv-tags"><?php foreach ($cv['interests'] as $x): ?><li><?= $e($x) ?></li><?php endforeach; ?></ul>
                </section>

                <section class="cv-sec <?= ($cv['pathways'] || $cv['pathway_extra']) ? '' : 'cv-sec-empty' ?>" id="cv-pathways">
                    <h2>Pathway ideas</h2>
                    <ul>
                        <?php foreach ($cv['pathways'] as $p): ?>
                            <li class="mb-2"><strong><?= $e($p['title']) ?></strong> <span class="text-muted">(<?= $e($p['field']) ?>)</span>
                                <?php if ($p['qualifications']): ?><br><span class="text-muted">Study: <?= $e(implode('; ', $p['qualifications'])) ?><?= $p['providers'] ? ' at ' . $e(implode(', ', $p['providers'])) : '' ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($cv['pathway_extra'] as $x): ?><li><?= $e($x) ?></li><?php endforeach; ?>
                    </ul>
                </section>

                <section class="cv-sec <?= $hasReco ? '' : 'cv-sec-empty' ?>" id="cv-recommendations">
                    <h2>Khetha recommendations</h2>
                    <ul>
                        <?php if ($rec['career_choice']): ?>
                            <li><strong>Career Choice (<?= $e($rec['career_choice']['code']) ?>):</strong> leans towards <?= $e(implode(', ', array_map('strtolower', $rec['career_choice']['leans']))) ?> work<?= $rec['career_choice']['top'] ? '. Top matches: ' . $e(implode(', ', $rec['career_choice']['top'])) : '' ?>.</li>
                        <?php endif; ?>
                        <?php if ($rec['subject_chooser']): ?>
                            <li><strong>Subject Chooser:</strong> aiming for
                                <?= implode(', ', array_map(fn($c) => $e($c['title']) . ($c['open'] === true ? ' (open with current subjects)' : ($c['open'] === false ? ' (needs a subject change)' : '')), $rec['subject_chooser'])) ?>.</li>
                        <?php endif; ?>
                        <?php if ($rec['job_fit']): ?>
                            <li><strong>Job Fit:</strong> <?= implode('; ', array_map(fn($j) => $e($j['title']) . ' &ndash; ' . $e($j['verdict']), $rec['job_fit'])) ?>.</li>
                        <?php endif; ?>
                    </ul>
                </section>

                <section class="cv-sec <?= $cv['achievements'] ? '' : 'cv-sec-empty' ?>" id="cv-achievements">
                    <h2>Achievements &amp; activities</h2>
                    <ul><?php foreach ($cv['achievements'] as $x): ?><li><?= $e($x) ?></li><?php endforeach; ?></ul>
                </section>
            </article>
            <?php endif; ?>
        </div>
    </body>
</html>
