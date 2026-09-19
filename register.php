<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/journey.php';
require_once __DIR__ . '/data/interests.php';
kp_session_start();

$error='';
$subjectOptions=['Mathematics','Mathematical Literacy','Physical Sciences','Life Sciences','IT','Computer Applications Technology','Accounting','Business Studies','Economics','Geography'];
$oldSubjects=(array)($_POST['subjects']??[]); $oldInterests=(array)($_POST['interests']??[]);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['csrf']??'')) $error=t('Your session expired. Refresh the page and try again.');
    else {
        $name=trim(preg_replace('/\s+/',' ',(string)($_POST['name']??'')));
        $email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??''); $grade=trim((string)($_POST['grade']??''));
        $subjects=array_values(array_intersect($subjectOptions,(array)($_POST['subjects']??[])));
        $consent=isset($_POST['consent']);
        $chips=[]; foreach ((array)($_POST['interests']??[]) as $v) { $label=kp_interest_label((string)$v); if($label!==null&&!in_array($label,$chips,true))$chips[]=$label; }
        if($name===''||mb_strlen($name)>200||!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($password)<8||!in_array($grade,['Grade 9','Grade 10','Grade 11','Grade 12','Post-school'],true)||count($subjects)<1||count($chips)<3||!$consent){
            $error=t('Please complete all required fields, select at least one subject, choose at least 3 interests and accept the consent statement.');
        } else {
            try {
                $db=kp_db(); if(!$db) throw new RuntimeException('Database unavailable');
                [$first,$last]=_account_split_name($name);
                $hash=password_hash($password,PASSWORD_DEFAULT);
                $consentAt=date('Y-m-d H:i:s');
                $stmt=$db->prepare('INSERT INTO users (firstName,lastName,email,passwordHash,grade,interests,consentAt) VALUES (?,?,?,?,?,?,?)');
                $interests=json_encode($chips,JSON_UNESCAPED_UNICODE);
                $stmt->bind_param('sssssss',$first,$last,$email,$hash,$grade,$interests,$consentAt);
                if(!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Could not create account'); }
                $userId=(int)$stmt->insert_id; $stmt->close();
                session_regenerate_id(true);
                $_SESSION['user']=['id'=>$userId,'name'=>$name,'email'=>$email,'grade'=>$grade];
                profile_reset();
                profile_update(['name'=>$name,'grade'=>$grade,'subjects'=>$subjects,'interests'=>$chips]);
                $prefs=['pushEnabled'=>false,'deadlineReminders'=>true,'assessmentReminders'=>true,'journeyTips'=>true]; kp_save_notification_preferences($userId,$prefs);
                kp_add_notification($userId,'Welcome to Khetha Path','Your profile is ready. Start with Subject Chooser, Career Choice or explore the directories.','welcome','dashboard.php');
                kp_log_event($userId,'profile_created',['grade'=>$grade,'subjects_count'=>count($subjects)]);
                header('Location: dashboard.php'); exit;
            } catch(Throwable $e) { error_log('Khetha registration failed: '.$e->getMessage()); $error=t('We could not create your account. Make sure MySQL is running and database/khetha_path.sql has been imported.'); }
        }
    }
}
?>
<!doctype html><html lang="<?=kp_lang()?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__.'/assets/pwa-head.php'; ?><title><?=t('Create your Khetha profile')?></title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="auth-page"><header class="topbar"><a class="brand kp-auth-brand" href="index.php"><img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha"><span class="kp-auth-divider"></span><img class="kp-auth-gov" src="assets/images/dhet-official-logo.png" alt="Department of Higher Education and Training"></a><?php include __DIR__.'/assets/lang-links.php'; ?><span class="status-pill"><?=t('Secure profile')?></span></header>
<main class="auth-layout"><section class="auth-intro"><p class="eyebrow"><?=t('CREATE YOUR KHETHA PROFILE')?></p><h1><?=t('Let’s make this personal.')?></h1><p class="lead"><?=t('Your answers become the starting point for a personalised career journey.')?></p><div class="privacy-card"><b>🔒 <?=t('Your information matters.')?></b><p><?=t('Your password is securely hashed. Your profile, assessments, saved choices and journey are linked to your account so you can continue across sessions.')?></p></div></section>
<section class="auth-card"><div class="step-label"><?=t('CREATE ACCOUNT')?> &nbsp; • &nbsp; <?=t('PERSONALISE')?></div><?php if($error):?><div class="error-box" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?>
<form method="post" id="onbForm"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
<label><?=t('Full name')?><input name="name" required autocomplete="name" value="<?=htmlspecialchars($_POST['name']??'')?>" placeholder="e.g. Lindiwe Mokoena"></label>
<label><?=t('Email')?><input type="email" name="email" required autocomplete="email" value="<?=htmlspecialchars($_POST['email']??'')?>" placeholder="you@example.com"></label>
<label><?=t('Password')?><input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="<?=t('At least 8 characters')?>"></label>
<label><?=t('What grade are you in?')?><select name="grade" required><option value=""><?=t('Choose…')?></option><?php foreach(['Grade 9','Grade 10','Grade 11','Grade 12','Post-school'] as $g):?><option value="<?=htmlspecialchars($g)?>" <?=($_POST['grade']??'')===$g?'selected':''?>><?=t($g)?></option><?php endforeach;?></select></label>
<label><?=t('Which subjects are you taking?')?> <small><?=t('Select all that apply')?></small></label><div class="subject-grid"><?php foreach($subjectOptions as $x):?><label class="subject-choice"><input type="checkbox" name="subjects[]" value="<?=htmlspecialchars($x)?>" <?=in_array($x,$oldSubjects,true)?'checked':''?>><span><?=t($x)?></span></label><?php endforeach;?></div>
<label><?=t('What are you interested in?')?> <small><?=t('Pick at least 3. This helps Khetha personalise your starting point.')?></small></label><?php foreach(kp_interest_groups() as $group=>$chips):?><div class="step-label" style="margin:10px 0 4px"><?=t($group)?></div><div class="subject-grid chip-grid"><?php foreach(array_keys($chips) as $chip):?><label class="subject-choice"><input type="checkbox" name="interests[]" value="<?=htmlspecialchars($chip)?>" <?=in_array($chip,$oldInterests,true)?'checked':''?>><span><?=t($chip)?></span></label><?php endforeach;?></div><?php endforeach;?>
<label class="consent-row"><input type="checkbox" name="consent" value="1" required><span><?=t('I understand that my information will be used to personalise my Khetha journey.')?></span></label>
<button class="primary-btn" type="submit"><?=t('Create My Khetha Profile')?> →</button></form><p class="auth-switch"><?=t('Already have an account?')?> <a href="login.php"><?=t('Sign in')?></a></p></section></main></body></html>
