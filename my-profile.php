<?php
// My Profile: the basics a learner can change after signing up — grade, Maths
// track and interest chips. Everything is saved to the shared profile.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/profile.php';
require_once __DIR__ . '/includes/csrf.php';
kp_require_auth();

$grades = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12', 'Post-school'];
$groups = kp_interest_groups();
$profile = profile_get();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = t('Your session expired. Go back, refresh the page and try again.');
    } else {
        $changes = [];
        if (in_array($_POST['grade'] ?? '', $grades, true)) $changes['grade'] = $_POST['grade'];
        if (in_array($_POST['maths_track'] ?? '', PROFILE_MATHS_TRACKS, true)) $changes['maths_track'] = $_POST['maths_track'];

        // Only real chips are accepted from the form. Anything else already in
        // the profile (free text typed at registration) is kept, not dropped.
        $picked = [];
        foreach ((array)($_POST['interests'] ?? []) as $v) {
            $label = is_string($v) ? kp_interest_label($v) : null;
            if ($label !== null && !in_array($label, $picked, true)) $picked[] = $label;
        }
        $extras = array_values(array_filter($profile['interests'], fn($i) => kp_interest_label((string)$i) === null));
        $changes['interests'] = array_merge($picked, $extras);

        profile_update($changes);
        header('Location: dashboard.php');
        exit;
    }
}

$selected = array_filter(array_map(fn($i) => kp_interest_label((string)$i), $profile['interests']));
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <title><?= t('My Profile') ?> — Khetha</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4" style="max-width:820px">
            <div class="mb-4">
                <h2><?= t('My Profile') ?></h2>
                <p class="text-muted"><?= t('Tell Khetha this once. Every page uses it, so you never answer the same question twice.') ?></p>
            </div>

            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

            <form method="post" action="my-profile.php">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?= t('What grade are you in?') ?></h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($grades as $g): ?>
                                <input type="radio" class="btn-check" name="grade" id="g-<?= md5($g) ?>" value="<?= $g ?>" autocomplete="off" <?= $profile['grade'] === $g ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary px-3" for="g-<?= md5($g) ?>"><?= t($g) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><?= t('Which mathematics subject are you taking?') ?></h5>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach (PROFILE_MATHS_TRACKS as $m): ?>
                                <input type="radio" class="btn-check" name="maths_track" id="m-<?= md5($m) ?>" value="<?= $m ?>" autocomplete="off" <?= $profile['maths_track'] === $m ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary px-3" for="m-<?= md5($m) ?>"><?= t($m) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-3" id="interests">
                    <div class="card-body">
                        <h5 class="card-title"><?= t('What are you interested in?') ?></h5>
                        <p class="text-muted small"><?= t('Pick at least 3. This helps Khetha rank careers before you take any quiz.') ?> <strong id="interestCount"></strong></p>
                        <?php foreach ($groups as $group => $chips): ?>
                            <h6 class="text-muted small text-uppercase mt-3"><?= t($group) ?></h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (array_keys($chips) as $chip): ?>
                                    <input type="checkbox" class="btn-check interest-check" name="interests[]" id="i-<?= md5($chip) ?>" value="<?= htmlspecialchars($chip) ?>" autocomplete="off" <?= in_array($chip, $selected, true) ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-secondary btn-sm" for="i-<?= md5($chip) ?>"><?= t($chip) ?></label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="d-grid d-sm-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-secondary"><?= t('Cancel') ?></a>
                    <button type="submit" class="btn btn-primary"><?= t('Save my profile') ?></button>
                </div>
            </form>
        </div>

        <script>
        (function(){
            var checks = document.querySelectorAll('.interest-check');
            var out = document.getElementById('interestCount');
            function update() {
                var n = Array.prototype.filter.call(checks, function(c){ return c.checked; }).length;
                out.textContent = n + ' picked' + (n < 3 ? ' (' + (3 - n) + ' more to go)' : '');
            }
            checks.forEach(function(c){ c.addEventListener('change', update); });
            update();
            if (location.hash === '#interests') document.getElementById('interests').scrollIntoView();
        })();
        </script>
    </body>
</html>
