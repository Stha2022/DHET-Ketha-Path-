<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/occupation-data.php';
require_once __DIR__ . '/includes/journey.php';
require_once __DIR__ . '/includes/csrf.php';
kp_require_auth();
$userId=kp_user_id();
$tab=$_GET['tab']??'careers'; if(!in_array($tab,['careers','qualifications','providers'],true))$tab='careers';
$q=trim((string)($_GET['q']??'')); $field=trim((string)($_GET['field']??'')); $type=trim((string)($_GET['type']??'')); $province=trim((string)($_GET['province']??''));
$careers=kp_occupations(); $quals=kp_qualifications(); $providers=kp_providers();
$fields=array_values(array_unique(array_map(fn($o)=>(string)$o['field'],$careers))); sort($fields);
$types=array_values(array_unique(array_map(fn($p)=>(string)$p['type'],$providers))); sort($types);
$provinces=[]; foreach($providers as $pr)foreach($pr['campuses'] as $c)$provinces[$c['province']]=true; $provinces=array_keys($provinces);sort($provinces);
if($q!=='')kp_log_event($userId,'directory_search',['tab'=>$tab,'query'=>$q]);
$filtered=[];
if($tab==='careers'){
 foreach($careers as $o){$hay=mb_strtolower($o['title'].' '.implode(' ',$o['alt_titles']).' '.$o['field'].' '.implode(' ',$o['tags']));if($q!==''&&!str_contains($hay,mb_strtolower($q)))continue;if($field!==''&&$o['field']!==$field)continue;$filtered[]=$o;}
 usort($filtered,fn($a,$b)=>strcmp($a['title'],$b['title']));
}elseif($tab==='qualifications'){
 foreach($quals as $x){$hay=mb_strtolower($x['title'].' '.$x['field']);if($q!==''&&!str_contains($hay,mb_strtolower($q)))continue;if($field!==''&&$x['field']!==$field)continue;$filtered[]=$x;} usort($filtered,fn($a,$b)=>strcmp($a['title'],$b['title']));
}else{
 foreach($providers as $x){$hay=mb_strtolower($x['name'].' '.$x['type'].' '.implode(' ',array_map(fn($c)=>$c['province'].' '.$c['town'],$x['campuses'])));if($q!==''&&!str_contains($hay,mb_strtolower($q)))continue;if($type!==''&&$x['type']!==$type)continue;if($province!==''&&!in_array($province,array_column($x['campuses'],'province'),true))continue;$filtered[]=$x;} usort($filtered,fn($a,$b)=>strcmp($a['name'],$b['name']));
}
$favs=[]; foreach(kp_favourites($userId) as $f)$favs[$f['itemType'].'|'.$f['itemID']]=true;
function dir_url(string $tab,array $extra=[]):string{return 'directories.php?'.http_build_query(array_merge(['tab'=>$tab],$extra));}
?>
<!doctype html><html lang="<?=kp_lang()?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__.'/assets/pwa-head.php'; ?><title>Explore — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><?php include __DIR__.'/assets/navbar.php'; ?><main id="main-content" class="dashboard narrow"><div class="page-head-row"><div><p class="eyebrow">NCAP-ALIGNED EXPLORE</p><h1>Find what comes next.</h1><p class="lead">Search careers, what to study and where to study — with filters designed for a phone screen.</p></div></div>
<div class="ncap-breadcrumb" style="margin:12px 0">🏛️ <b>NCAP structure:</b> Careers · What to Study · Where to Study</div>
<nav class="directory-tabs" aria-label="Explore categories"><a class="<?= $tab==='careers'?'active':''?>" href="<?=dir_url('careers')?>">💼 Careers</a><a class="<?= $tab==='qualifications'?'active':''?>" href="<?=dir_url('qualifications')?>">🎓 What to Study</a><a class="<?= $tab==='providers'?'active':''?>" href="<?=dir_url('providers')?>">📍 Where to Study</a></nav>
<form class="directory-search" method="get"><input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>"><label class="sr-only" for="directory-q">Search</label><input id="directory-q" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search careers, qualifications or providers…" autocomplete="off"><button class="primary-btn" type="submit">Search</button></form>
<div class="directory-filters"><select onchange="location.href=this.value" aria-label="Filter field"><option value="<?=dir_url($tab,['q'=>$q])?>">All fields</option><?php foreach($fields as $x):?><option value="<?=dir_url($tab,['q'=>$q,'field'=>$x])?>" <?=($field===$x?'selected':'')?>><?=htmlspecialchars($x)?></option><?php endforeach;?></select><?php if($tab==='providers'):?><select onchange="location.href=this.value" aria-label="Filter provider type"><option value="<?=dir_url($tab,['q'=>$q,'province'=>$province])?>">All provider types</option><?php foreach($types as $x):?><option value="<?=dir_url($tab,['q'=>$q,'province'=>$province,'type'=>$x])?>" <?=($type===$x?'selected':'')?>><?=htmlspecialchars(ucfirst($x))?></option><?php endforeach;?></select><select onchange="location.href=this.value" aria-label="Filter province"><option value="<?=dir_url($tab,['q'=>$q,'type'=>$type])?>">All provinces</option><?php foreach($provinces as $x):?><option value="<?=dir_url($tab,['q'=>$q,'type'=>$type,'province'=>$x])?>" <?=($province===$x?'selected':'')?>><?=htmlspecialchars($x)?></option><?php endforeach;?></select><?php endif;?></div>
<div class="directory-result-count"><?=count($filtered)?> result<?=count($filtered)===1?'':'s'?></div>
<div class="directory-cards"><?php if(!$filtered):?><div class="empty-state"><span>🔎</span><b>No matches yet.</b><p>Try a broader search or remove a filter.</p></div><?php else: foreach($filtered as $x):
 if($tab==='careers'){$id=$x['id'];$itemType='career';$label=$x['title'];$url='occupation.php?id='.urlencode($id);$meta=$x['field'].' · '.ucfirst($x['demand']).' demand';$desc=$x['description'];}
 elseif($tab==='qualifications'){$id=$x['id'];$itemType='qualification';$label=$x['title'];$url=dir_url('qualifications',['q'=>$x['title']]);$meta='NQF '.$x['nqf_level'].' · '.$x['duration'];$desc=$x['field'].' · APS '.($x['entry_requirements']['aps']??'varies');}
 else {$id=$x['id'];$itemType='provider';$label=$x['name'];$url=$x['contact']['website']??dir_url('providers',['q'=>$x['name']]);$meta=ucfirst($x['type']).' · '.implode(', ',array_unique(array_map(fn($c)=>$c['province'],$x['campuses'])));$desc='Qualifications: '.count($x['qualification_ids']).' listed in this prototype directory.';}
 $isFav=isset($favs[$itemType.'|'.$id]);
 ?><article class="directory-card"><div class="directory-card-main"><span class="directory-icon"><?= $tab==='careers'?'💼':($tab==='qualifications'?'🎓':'📍')?></span><div><h2><?=htmlspecialchars($label)?></h2><p class="directory-meta"><?=htmlspecialchars($meta)?></p><p><?=htmlspecialchars($desc)?></p><?php if($tab==='careers'&&!empty($x['tags'])):?><div class="tag-row"><?php foreach($x['tags'] as $tag):?><span><?=htmlspecialchars(str_replace('_',' ',$tag))?></span><?php endforeach;?></div><?php endif;?></div></div><div class="directory-card-actions"><a class="ghost-btn" href="<?=htmlspecialchars($url)?>" <?=($tab==='providers'&&str_starts_with((string)$url,'http'))?'target="_blank" rel="noopener"':''?>>View</a><form method="post" action="favourites.php"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="item_type" value="<?=htmlspecialchars($itemType)?>"><input type="hidden" name="item_id" value="<?=htmlspecialchars($id)?>"><input type="hidden" name="label" value="<?=htmlspecialchars($label)?>"><input type="hidden" name="back" value="<?=htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'directories.php')?>"><button class="save-btn <?= $isFav?'saved':''?>" type="submit" aria-label="<?= $isFav?'Remove':'Save'?> <?=htmlspecialchars($label)?>">♡ <?= $isFav?'Saved':'Save'?></button></form></div></article><?php endforeach;endif;?></div>
<div class="source-note">Prototype reference content. Production deployment should synchronise the same structure with approved NCAP/DHET data and source versions.</div></main></body></html>
