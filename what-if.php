<?php
session_start();
require_once __DIR__ . '/assets/lang.php';

// Scenario copy is rendered client-side by assets/js/app.js, so it is
// translated here and handed over as JSON.
$scenarios = [
    'notqualify' => [
        'title'  => t('Your goal can have more than one route.'),
        'text'   => t('Instead of stopping at the first requirement, Khetha can help you compare adjacent qualifications and progression routes that continue toward a related career goal.'),
        'routes' => [t('Alternative qualification route'), t('Related career route'), t('Progression / bridging route')],
    ],
    'subjects' => [
        'title'  => t('Your pathway changes with your subjects.'),
        'text'   => t('Khetha can flag where subject requirements matter, then guide you toward routes that fit your updated subject profile rather than showing you the same list.'),
        'routes' => [t('Re-check subject requirements'), t('Explore compatible qualifications'), t('Review related careers')],
    ],
    'connectivity' => [
        'title'  => t('Your journey should not disappear when your data does.'),
        'text'   => t('The mobile experience can keep key pathway information, saved careers and next actions available offline, then sync when connectivity returns.'),
        'routes' => [t('Saved journey offline'), t('Low-data content mode'), t('Sync when connected')],
    ],
    'provider' => [
        'title'  => t('You can compare study routes.'),
        'text'   => t('Khetha can keep the career goal fixed while allowing the learner to compare different qualification and provider options.'),
        'routes' => [t('Compare qualifications'), t('Compare providers'), t('Save a preferred route')],
    ],
];
$i18n = ['scenarios' => $scenarios, 'explore' => t('Explore →')];
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
<title><?= t('What If?') ?> — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/assets/navbar.php'; ?>
<main class="dashboard narrow">
<p class="eyebrow"><?= t('PATHWAY INTELLIGENCE') ?></p>
<h1><?= t('What if my first route changes?') ?></h1>
<p class="lead"><?= t('Real life doesn’t follow a straight line. Pick a scenario and Khetha will show how your pathway could adapt.') ?></p>

<div class="scenario-grid">
<button class="scenario" data-scenario="notqualify"><span>01</span><b><?= t('I don’t qualify for my first choice') ?></b><small><?= t('Explore alternative routes that still move toward the goal.') ?></small></button>
<button class="scenario" data-scenario="subjects"><span>02</span><b><?= t('My subjects change') ?></b><small><?= t('See which parts of the pathway may need to change.') ?></small></button>
<button class="scenario" data-scenario="connectivity"><span>03</span><b><?= t('I have limited connectivity') ?></b><small><?= t('Prioritise saved, low-data journey information.') ?></small></button>
<button class="scenario" data-scenario="provider"><span>04</span><b><?= t('I want another study route') ?></b><small><?= t('Compare qualification and provider options.') ?></small></button>
</div>

<section id="adapterResult" class="adapter-result hidden">
 <div class="result-head"><div class="avatar">K</div><div><small><?= t('KHETHA ADAPTED YOUR PATH') ?></small><h2 id="resultTitle"></h2></div></div>
 <p id="resultText"></p>
 <div id="routeCards"></div>
 <div class="source-note"><?= t('ⓘ Prototype scenario. In the production app, route logic and study information would be grounded in approved NCAP/DHET data and clearly explain the source.') ?></div>
</section>

<a class="ghost-btn" href="my-path.php"><?= t('← Back to My Journey') ?></a>
</main>
</div>
<script>window.KP_I18N = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="assets/js/app.js"></script>
</body>
</html>
