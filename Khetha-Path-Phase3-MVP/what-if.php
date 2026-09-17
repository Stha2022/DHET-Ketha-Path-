<?php
session_start();
$name = $_SESSION['name'] ?? 'Lindi';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>What If? — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
<header class="topbar">
 <a class="brand" href="my-path.php"><span class="brand-mark">K</span><span>Khetha <b>Path</b></span></a>
 <span class="status-pill">Pathway Adapter</span>
</header>
<main class="dashboard narrow">
<p class="eyebrow">PATHWAY INTELLIGENCE</p>
<h1>What if my first route changes?</h1>
<p class="lead">Real life doesn’t follow a straight line. Pick a scenario and Khetha will show how your pathway could adapt.</p>

<div class="scenario-grid">
<button class="scenario" data-scenario="notqualify"><span>01</span><b>I don’t qualify for my first choice</b><small>Explore alternative routes that still move toward the goal.</small></button>
<button class="scenario" data-scenario="subjects"><span>02</span><b>My subjects change</b><small>See which parts of the pathway may need to change.</small></button>
<button class="scenario" data-scenario="connectivity"><span>03</span><b>I have limited connectivity</b><small>Prioritise saved, low-data journey information.</small></button>
<button class="scenario" data-scenario="provider"><span>04</span><b>I want another study route</b><small>Compare qualification and provider options.</small></button>
</div>

<section id="adapterResult" class="adapter-result hidden">
 <div class="result-head"><div class="avatar">K</div><div><small>KHETHA ADAPTED YOUR PATH</small><h2 id="resultTitle"></h2></div></div>
 <p id="resultText"></p>
 <div id="routeCards"></div>
 <div class="source-note">ⓘ Prototype scenario. In the production app, route logic and directory information would be grounded in approved NCAP/DHET data and clearly explain the source.</div>
</section>

<a class="ghost-btn" href="my-path.php">← Back to My Journey</a>
</main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>