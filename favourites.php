<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/journey.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/occupation-data.php';
kp_require_auth(); $userId=kp_user_id();
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf']??'') && ($_POST['action']??'')==='toggle'){
  $type=trim((string)($_POST['item_type']??'career')); $id=trim((string)($_POST['item_id']??'')); $label=trim((string)($_POST['label']??$id));
  if(in_array($type,['career','qualification','provider'],true)&&$id!=='') kp_favourite_toggle($userId,$type,$id,$label);
  kp_log_event($userId,'favourite_toggled',['type'=>$type,'item_id'=>$id]);
  // Only ever back to a page in this app, never off-site.
  $back=(string)($_POST['back']??''); $q=parse_url($back,PHP_URL_QUERY); $back=basename((string)parse_url($back,PHP_URL_PATH)).($q?'?'.$q:''); if(!preg_match('/^[a-z0-9-]+\.php(\?[\w=&.%-]*)?(#[\w-]*)?$/i',$back)) $back='favourites.php';
  header('Location: '.$back); exit;
}
$n=profile_get()['name']?:t('Learner'); $rows=kp_favourites($userId);
?>
<!doctype html><html lang="<?=kp_lang()?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__.'/assets/pwa-head.php'; ?><title><?=t('Favourites')?> — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css"></head><body><?php include __DIR__.'/assets/navbar.php'; ?><main id="main-content" class="dashboard narrow"><p class="eyebrow">SAVED JOURNEY</p><h1><?=t('{name}’s favourites',['name'=>$n])?></h1><p class="lead">Keep the careers, qualifications and providers you want to revisit in one place.</p>
<?php if(!$rows):?><div class="empty-state"><span>♡</span><b><?=t('Your saved options will appear here.')?></b><p><?=t('Save careers, qualifications and providers to revisit.')?></p><a class="primary-btn center" href="directories.php">Start browsing →</a></div><?php else:?><div class="favourites-list"><?php foreach($rows as $f):?><article class="favourite-card"><span class="directory-icon"><?= $f['itemType']==='career'?'💼':($f['itemType']==='qualification'?'🎓':'📍')?></span><div><span class="favourite-type"><?=htmlspecialchars(ucfirst($f['itemType']))?></span><h2><?=htmlspecialchars($f['label']?:$f['itemID'])?></h2><small>Saved <?=htmlspecialchars(date('j M Y',strtotime($f['createdAt'])))?></small></div><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="item_type" value="<?=htmlspecialchars($f['itemType'])?>"><input type="hidden" name="item_id" value="<?=htmlspecialchars($f['itemID'])?>"><input type="hidden" name="label" value="<?=htmlspecialchars($f['label'])?>"><button class="save-btn saved" type="submit">♥ Remove</button></form></article><?php endforeach;?></div><a class="primary-btn" href="directories.php" style="display:inline-block;margin-top:16px">Explore more →</a><?php endif;?></main></body></html>
