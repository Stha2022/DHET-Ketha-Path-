<?php
require_once __DIR__ . '/../database/db-connection.php';
require_once __DIR__ . '/profile.php';

function kp_log_event(int $userId, string $type, array $data = []): void {
    $db = kp_db();
    if (!$db || $userId <= 0) return;
    $type = mb_substr(trim($type), 0, 80);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = @$db->prepare('INSERT INTO learner_content_interactions (userID,eventType,eventData) VALUES (?,?,?)');
    if ($stmt) { $stmt->bind_param('iss', $userId, $type, $json); $stmt->execute(); $stmt->close(); }
}


function kp_record_assessment(int $userId,string $source,array $payload=[],array $derived=[]): bool {
    $db=kp_db(); if(!$db||!$userId)return false;
    $p=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); $d=json_encode($derived,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $stmt=@$db->prepare('INSERT INTO assessmentResults (userID,source,payload,derived) VALUES (?,?,?,?)'); if(!$stmt)return false;
    $stmt->bind_param('isss',$userId,$source,$p,$d);$ok=$stmt->execute();$stmt->close();return $ok;
}

function kp_notification_preferences(int $userId): array {
    $defaults = ['pushEnabled'=>false,'deadlineReminders'=>true,'assessmentReminders'=>true,'journeyTips'=>true];
    $db = kp_db();
    if (!$db || !$userId) return $defaults;
    $stmt = @$db->prepare('SELECT pushEnabled,deadlineReminders,assessmentReminders,journeyTips FROM notification_preferences WHERE userID=? LIMIT 1');
    if (!$stmt) return $defaults;
    $stmt->bind_param('i',$userId); $stmt->execute(); $res=$stmt->get_result(); $row=$res?$res->fetch_assoc():null; $stmt->close();
    if (!$row) return $defaults;
    return [
        'pushEnabled'=>(bool)$row['pushEnabled'],
        'deadlineReminders'=>(bool)$row['deadlineReminders'],
        'assessmentReminders'=>(bool)$row['assessmentReminders'],
        'journeyTips'=>(bool)$row['journeyTips'],
    ];
}

function kp_save_notification_preferences(int $userId, array $values): bool {
    $db = kp_db(); if (!$db || !$userId) return false;
    $push = !empty($values['pushEnabled']) ? 1 : 0;
    $dead = !empty($values['deadlineReminders']) ? 1 : 0;
    $assess = !empty($values['assessmentReminders']) ? 1 : 0;
    $tips = !empty($values['journeyTips']) ? 1 : 0;
    $stmt = @$db->prepare('INSERT INTO notification_preferences (userID,pushEnabled,deadlineReminders,assessmentReminders,journeyTips) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE pushEnabled=VALUES(pushEnabled),deadlineReminders=VALUES(deadlineReminders),assessmentReminders=VALUES(assessmentReminders),journeyTips=VALUES(journeyTips)');
    if (!$stmt) return false;
    $stmt->bind_param('iiiii',$userId,$push,$dead,$assess,$tips); $ok=$stmt->execute(); $stmt->close(); return $ok;
}

function kp_unread_notification_count(int $userId): int {
    $db=kp_db(); if (!$db || !$userId) return 0;
    $stmt=@$db->prepare('SELECT COUNT(*) c FROM notifications WHERE userID=? AND readAt IS NULL AND (scheduledAt IS NULL OR scheduledAt<=NOW())');
    if (!$stmt) return 0; $stmt->bind_param('i',$userId); $stmt->execute(); $res=$stmt->get_result(); $row=$res?$res->fetch_assoc():null; $stmt->close(); return (int)($row['c']??0);
}

function kp_add_notification(int $userId, string $title, string $message, string $type='journey', ?string $url=null, ?string $scheduledAt=null): bool {
    $db=kp_db(); if (!$db || !$userId) return false;
    // Avoid duplicate reminders with the same title for one learner.
    $check=@$db->prepare('SELECT notificationID FROM notifications WHERE userID=? AND title=? AND type=? LIMIT 1');
    if ($check) { $check->bind_param('iss',$userId,$title,$type); $check->execute(); $res=$check->get_result(); $exists=$res&&$res->fetch_assoc(); $check->close(); if ($exists) return true; }
    $stmt=@$db->prepare('INSERT INTO notifications (userID,title,message,type,actionURL,scheduledAt) VALUES (?,?,?,?,?,?)');
    if (!$stmt) return false;
    $stmt->bind_param('isssss',$userId,$title,$message,$type,$url,$scheduledAt); $ok=$stmt->execute(); $stmt->close(); return $ok;
}

function kp_seed_journey_reminders(int $userId): void {
    $p = profile_get();
    $prefs = kp_notification_preferences($userId);
    if ($prefs['assessmentReminders']) {
        if ($p['career_quiz']['code'] === '') kp_add_notification($userId,'Complete Career Choice','Finish Career Choice so Khetha can connect your interests to career options.','assessment','career-quiz.php');
        if (empty($p['intended_careers']) || $p['home_language']==='' || $p['fal']==='' || $p['maths_track']==='') kp_add_notification($userId,'Complete Subject Chooser','Add your subjects and pathway choices so Khetha can check which doors your current subjects open.','assessment','subject.php');
        if (empty($p['job_fit'])) kp_add_notification($userId,'Try Job Fit','Check a career against practical work values, context and your current strengths.','assessment','occupation.php?fit=1');
    }
    if ($prefs['journeyTips']) {
        kp_add_notification($userId,'Explore and save','Save careers, qualifications or providers you want to revisit.','tip','directories.php');
        kp_add_notification($userId,'Your weekly Khetha check-in','Come back to review your next step, saved choices and pathway progress.','journey','my-path.php',date('Y-m-d H:i:s',strtotime('+7 days')));
    }
}

function kp_get_notifications(int $userId, int $limit=30): array {
    $db=kp_db(); if (!$db || !$userId) return [];
    $limit=max(1,min(100,$limit));
    $sql="SELECT notificationID,title,message,type,actionURL,scheduledAt,readAt,createdAt FROM notifications WHERE userID=? AND (scheduledAt IS NULL OR scheduledAt<=NOW()) ORDER BY (readAt IS NULL) DESC, COALESCE(scheduledAt,createdAt) DESC LIMIT $limit";
    $stmt=@$db->prepare($sql); if (!$stmt) return [];
    $stmt->bind_param('i',$userId); $stmt->execute(); $res=$stmt->get_result(); $rows=$res?$res->fetch_all(MYSQLI_ASSOC):[]; $stmt->close(); return $rows;
}

function kp_mark_notification_read(int $userId, int $notificationId): bool {
    $db=kp_db(); if (!$db || !$userId || !$notificationId) return false;
    $stmt=@$db->prepare('UPDATE notifications SET readAt=NOW() WHERE notificationID=? AND userID=?');
    if (!$stmt) return false; $stmt->bind_param('ii',$notificationId,$userId); $ok=$stmt->execute(); $stmt->close(); return $ok;
}

function kp_mark_all_notifications_read(int $userId): bool {
    $db=kp_db(); if (!$db || !$userId) return false;
    $stmt=@$db->prepare('UPDATE notifications SET readAt=NOW() WHERE userID=? AND readAt IS NULL');
    if (!$stmt) return false; $stmt->bind_param('i',$userId); $ok=$stmt->execute(); $stmt->close(); return $ok;
}

function kp_favourite_toggle(int $userId,string $type,string $itemId,string $label,array $metadata=[]): bool {
    $db=kp_db(); if (!$db || !$userId || $itemId==='') return false;
    $stmt=@$db->prepare('SELECT favouriteID FROM favourites WHERE userID=? AND itemType=? AND itemID=? LIMIT 1');
    if (!$stmt) return false; $stmt->bind_param('iss',$userId,$type,$itemId); $stmt->execute(); $res=$stmt->get_result(); $row=$res?$res->fetch_assoc():null; $stmt->close();
    if ($row) {
        $id=(int)$row['favouriteID']; $del=@$db->prepare('DELETE FROM favourites WHERE favouriteID=? AND userID=?'); if(!$del)return false; $del->bind_param('ii',$id,$userId);$ok=$del->execute();$del->close(); return $ok;
    }
    $json=json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ins=@$db->prepare('INSERT INTO favourites (userID,itemType,itemID,label,metadata) VALUES (?,?,?,?,?)'); if(!$ins)return false; $ins->bind_param('issss',$userId,$type,$itemId,$label,$json);$ok=$ins->execute();$ins->close(); return $ok;
}

function kp_favourites(int $userId): array {
    $db=kp_db(); if (!$db || !$userId) return [];
    $stmt=@$db->prepare('SELECT favouriteID,itemType,itemID,label,metadata,createdAt FROM favourites WHERE userID=? ORDER BY createdAt DESC'); if(!$stmt)return[]; $stmt->bind_param('i',$userId);$stmt->execute();$res=$stmt->get_result();$rows=$res?$res->fetch_all(MYSQLI_ASSOC):[];$stmt->close();return $rows;
}
