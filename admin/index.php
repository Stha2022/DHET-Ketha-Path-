<?php
$metrics = [
 ["Approved content sources","0","Connect NCAP/DHET API"],
 ["Journey events","Demo","Audit trail ready"],
 ["AI mode","Controlled prototype","Grounded retrieval in production"],
 ["Human escalation","Designed","Career practitioner channel"]
];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Khetha Path — Governance Demo</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body><div class="app-shell"><header class="topbar"><a class="brand" href="../index.php"><span class="brand-mark">K</span><span>Khetha <b>Path</b></span></a><span class="status-pill">Governance Demo</span></header>
<main class="dashboard narrow"><p class="eyebrow">DEMO ADMIN VIEW</p><h1>Trust & governance</h1><p class="lead">The AI companion is only useful if its guidance can be traced back to approved information.</p>
<div class="three-col"><?php foreach($metrics as $m): ?><section class="panel"><p class="eyebrow"><?=htmlspecialchars($m[0])?></p><h2><?=htmlspecialchars($m[1])?></h2><p class="muted"><?=htmlspecialchars($m[2])?></p></section><?php endforeach; ?></div>
<section class="adapter-result" style="margin-top:20px"><b>Judge demo point:</b> This layer is designed for source approval, auditability, consent, responsible AI and human oversight — not just a chatbot interface.</section>
</main></div></body></html>