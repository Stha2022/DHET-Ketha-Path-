<?php session_start(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Khetha Path</title><link rel="manifest" href="manifest.json"><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<header class="topbar"><a class="brand" href="index.php">KHETHA <span>PATH</span></a><span class="tag">Your career. Your journey. Your next step.</span></header>
<main class="welcome">
<section class="welcome-copy"><div class="eyebrow">DHET • CAREER GUIDANCE</div><h1>Meet your career companion.</h1><p>Khetha Path helps you explore what you could study, where your interests can take you, and what to do next.</p><div class="offline-pill">● Works with limited connectivity</div></section>
<section class="companion">
<div class="avatar">K</div><div class="bubble"><strong id="greeting">Hi! 👋 I'm Khetha.</strong><p>What's your name? I'll help you start your career journey.</p></div>
<form id="welcomeForm"><input id="name" required maxlength="50" placeholder="Enter your name"><button class="btn primary" type="submit">Let's start →</button></form>
<div id="followup" class="bubble hidden"><strong id="personalGreeting"></strong><p>What subjects are you currently taking?</p><div class="chips"><button type="button" data-sub="Mathematics">Mathematics</button><button type="button" data-sub="Physical Sciences">Physical Sciences</button><button type="button" data-sub="IT">IT / Computer Applications</button><button type="button" data-sub="Business Studies">Business Studies</button><button type="button" data-sub="Languages">Languages</button></div><button id="continueBtn" class="btn primary hidden">Continue →</button></div>
</section></main>
<footer>Prototype • GovTech 2026 Hackathon</footer><script src="assets/js/app.js"></script></body></html>