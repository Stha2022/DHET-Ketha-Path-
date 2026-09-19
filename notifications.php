<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/journey.php';
require_once __DIR__ . '/includes/csrf.php';
kp_require_auth();
$userId=kp_user_id();
kp_seed_journey_reminders($userId);
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['csrf']??'')){
    if(($_POST['action']??'')==='read_all') kp_mark_all_notifications_read($userId);
    if(($_POST['action']??'')==='read' && ctype_digit((string)($_POST['id']??''))) kp_mark_notification_read($userId,(int)$_POST['id']);
    header('Location: notifications.php'); exit;
}
$items=kp_get_notifications($userId,50); $unread=kp_unread_notification_count($userId);
$icon=['welcome'=>'👋','assessment'=>'📝','tip'=>'💡','deadline'=>'📅','journey'=>'🛣️'];
?>
<!doctype html><html lang="<?=kp_lang()?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__.'/assets/pwa-head.php'; ?><title>Notifications — Khetha Path</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><?php include __DIR__.'/assets/navbar.php'; ?><main id="main-content" class="dashboard narrow notification-page"><div class="page-head-row"><div><p class="eyebrow">YOUR KHETHA REMINDERS</p><h1>Keep your journey moving.</h1><p class="lead">Reminders and next steps linked to your saved profile.</p></div><div class="notification-count"><?= (int)$unread ?> new</div></div>
<div class="notification-actions"><button class="primary-btn" type="button" id="enableReminders">🔔 Enable device reminders</button><?php if($items):?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="read_all"><button class="ghost-btn" type="submit">Mark all as read</button></form><?php endif;?><span id="reminderStatus" role="status"></span></div>
<div class="notification-list"><?php if(!$items):?><div class="empty-state"><span>🎉</span><b>You’re all caught up.</b><p>When Khetha has a useful next step or reminder, it will appear here.</p></div><?php else: foreach($items as $n):?><article class="notification-item <?= $n['readAt'] ? 'read' : 'unread' ?>"><span class="notification-dot" aria-hidden="true"><?=htmlspecialchars($icon[$n['type']]??'🔔')?></span><div class="notification-copy"><div class="notification-title-row"><b><?=htmlspecialchars($n['title'])?></b><?php if(!$n['readAt']):?><span class="new-badge">NEW</span><?php endif;?></div><p><?=htmlspecialchars($n['message'])?></p><small><?=htmlspecialchars(date('j M Y · H:i',strtotime($n['scheduledAt']?:$n['createdAt'])))?></small><?php if(!empty($n['actionURL'])):?><a href="<?=htmlspecialchars($n['actionURL'])?>" class="notification-action">Open next step →</a><?php endif;?></div><?php if(!$n['readAt']):?><form method="post" class="notification-read"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="read"><input type="hidden" name="id" value="<?= (int)$n['notificationID'] ?>"><button type="submit" aria-label="Mark notification as read">✓</button></form><?php endif;?></article><?php endforeach; endif;?></div>
<div class="source-note">Khetha stores notification records with your account. Device notifications are optional and require your permission. On the native mobile build, Capacitor Local Notifications can schedule reminders on the device.</div></main><script src="assets/js/native-notifications.js"></script></body></html>
