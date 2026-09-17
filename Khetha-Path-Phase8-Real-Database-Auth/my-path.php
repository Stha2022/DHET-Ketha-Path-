<?php
session_start();
$name = $_SESSION['name'] ?? 'Lindi';
$subjects = $_SESSION['subjects'] ?? ['Mathematics','IT'];
$interest = $_SESSION['interest'] ?? 'building apps';
$grade = $_SESSION['grade'] ?? 'Grade 11';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Path — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
<header class="topbar">
 <a class="brand" href="index.php"><span class="brand-mark">K</span><span>Khetha <b>Path</b></span></a>
 <span class="status-pill" id="networkStatus">● Connected</span>
</header>

<main class="dashboard">
<section class="welcome-row">
  <div><p class="eyebrow">MY CAREER JOURNEY</p><h1>Hi, <?=htmlspecialchars($name)?> 👋</h1>
  <p class="muted">You’re in <b><?=htmlspecialchars($grade)?></b>. Khetha is adapting your journey around your starting point.</p></div>
  <a class="ghost-btn" href="ask.html">Ask Khetha ✦</a>
</section>

<section class="journey-strip">
  <div class="journey-step active"><b>01</b><span>Know Me</span></div>
  <div class="journey-line"></div>
  <div class="journey-step active"><b>02</b><span>Explore</span></div>
  <div class="journey-line"></div>
  <div class="journey-step"><b>03</b><span>Prepare</span></div>
  <div class="journey-line"></div>
  <div class="journey-step"><b>04</b><span>Apply</span></div>
</section>

<div class="three-col">
<section class="panel">
 <div class="panel-title"><span>✦</span><div><small>KHETHA'S READ</small><h2>Your starting point</h2></div></div>
 <div class="profile-box">
   <p><span>Subjects</span><?=htmlspecialchars(implode(' • ', $subjects))?></p>
   <p><span>Interest</span><?=htmlspecialchars($interest)?></p>
 </div>
 <div class="insight"><b>Why this matters</b><p>Your starting point changes which routes you should investigate. Khetha keeps your context attached to the journey.</p></div>
</section>

<section class="panel path-panel">
 <div class="panel-title"><span>◎</span><div><small>ADAPTIVE PATHWAY</small><h2>Software Development</h2></div></div>
 <p class="muted">A prototype pathway based on your current profile. In production, pathway data would be grounded in approved NCAP/DHET content.</p>
 <div class="path-timeline">
  <div class="path-node done"><b>NOW</b><span>Review subject requirements</span></div>
  <div class="path-node"><b>NEXT</b><span>Explore qualifications</span></div>
  <div class="path-node"><b>THEN</b><span>Compare learning providers</span></div>
  <div class="path-node"><b>LATER</b><span>Explore opportunities</span></div>
 </div>
 <a class="primary-btn center" href="what-if.php">Try “What If?” →</a>
</section>

<section class="panel">
 <div class="panel-title"><span>◈</span><div><small>YOUR NEXT ACTION</small><h2>One step at a time</h2></div></div>
 <div class="next-card">
   <span class="big-number">1</span><div><b>Compare qualification routes</b><p>See different study options that can lead toward your chosen career.</p></div>
 </div>
 <button class="secondary-btn" onclick="markComplete(this)">Mark as explored ✓</button>
 <div class="offline-card">📥 <b>Your journey can travel with you.</b><br><span>Saved journey content is available offline in the PWA prototype.</span></div>
</section>
</div>
</main>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>