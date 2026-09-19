<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/journey.php';
require_once __DIR__ . '/../includes/csrf.php';
kp_require_auth();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$userId=kp_user_id();
if($_SERVER['REQUEST_METHOD']==='GET'){
    kp_seed_journey_reminders($userId);
    $db=kp_db(); $scheduled=[];
    if($db){$stmt=@$db->prepare('SELECT notificationID,title,message,type,actionURL,scheduledAt FROM notifications WHERE userID=? AND scheduledAt IS NOT NULL AND scheduledAt>NOW() ORDER BY scheduledAt ASC LIMIT 20'); if($stmt){$stmt->bind_param('i',$userId);$stmt->execute();$res=$stmt->get_result();$scheduled=$res?$res->fetch_all(MYSQLI_ASSOC):[];$stmt->close();}}
    echo json_encode(['ok'=>true,'unread'=>kp_unread_notification_count($userId),'items'=>kp_get_notifications($userId,30),'scheduled'=>$scheduled]); exit;
}
if(!csrf_check($_POST['csrf']??'')){http_response_code(419);echo json_encode(['ok'=>false,'error'=>'Invalid session token']);exit;}
$action=$_POST['action']??'';
if($action==='enable'){
    kp_save_notification_preferences($userId,['pushEnabled'=>true,'deadlineReminders'=>true,'assessmentReminders'=>true,'journeyTips'=>true]);
    kp_log_event($userId,'reminders_enabled'); echo json_encode(['ok'=>true]); exit;
}
if($action==='disable'){kp_save_notification_preferences($userId,['pushEnabled'=>false,'deadlineReminders'=>true,'assessmentReminders'=>true,'journeyTips'=>true]);echo json_encode(['ok'=>true]);exit;}
if($action==='prefs'){
    $ok=kp_save_notification_preferences($userId,[
      'pushEnabled'=>!empty($_POST['pushEnabled']),
      'deadlineReminders'=>!empty($_POST['deadlineReminders']),
      'assessmentReminders'=>!empty($_POST['assessmentReminders']),
      'journeyTips'=>!empty($_POST['journeyTips'])
    ]);
    echo json_encode(['ok'=>$ok]); exit;
}
if($action==='read' && ctype_digit((string)($_POST['id']??''))){kp_mark_notification_read($userId,(int)$_POST['id']);echo json_encode(['ok'=>true,'unread'=>kp_unread_notification_count($userId)]);exit;}
http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Unknown action']);
