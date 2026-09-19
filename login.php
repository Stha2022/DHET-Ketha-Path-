<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/journey.php';
kp_session_start();
if (!empty($_SESSION['user']['id'])) { header('Location: dashboard.php'); exit; }
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['csrf']??'')) $error=t('Your session expired. Refresh the page and try again.');
    else {
        $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password==='') $error=t('Please enter your email and password.');
        else try {
            $db=kp_db(); if(!$db) throw new RuntimeException('Database unavailable');
            $stmt=$db->prepare('SELECT userID,firstName,lastName,email,passwordHash,grade FROM users WHERE email=? LIMIT 1'); $stmt->bind_param('s',$email); $stmt->execute(); $res=$stmt->get_result(); $u=$res?$res->fetch_assoc():null; $stmt->close();
            if(!$u || !password_verify($password,(string)$u['passwordHash'])) $error=t('The email or password is incorrect.');
            else {
                session_regenerate_id(true); $name=trim($u['firstName'].' '.$u['lastName']);
                $_SESSION['user']=['id'=>(int)$u['userID'],'name'=>$name,'email'=>$u['email'],'grade'=>kp_grade_from_db((string)$u['grade'])];
                profile_get(); kp_log_event((int)$u['userID'],'login',['source'=>'web']);
                header('Location: dashboard.php'); exit;
            }
        } catch(Throwable $e){ error_log('Khetha login failed: '.$e->getMessage()); $error=t('We could not connect to your account right now. Check that MySQL is running.'); }
    }
}
?>
<!doctype html><html lang="<?=kp_lang()?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><?php include __DIR__.'/assets/pwa-head.php'; ?><title><?=t('Sign in')?> — Khetha</title><link rel="stylesheet" href="assets/css/style.css"></head><body class="auth-page"><header class="topbar"><a class="brand kp-auth-brand" href="index.php"><img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha"><span class="kp-auth-divider"></span><img class="kp-auth-gov" src="assets/images/dhet-official-logo.png" alt="Department of Higher Education and Training"></a><?php include __DIR__.'/assets/lang-links.php'; ?><a class="ghost-btn" href="register.php"><?=t('Create account')?></a></header><main class="auth-layout"><section class="auth-intro"><p class="eyebrow"><?=t('WELCOME BACK')?></p><h1><?=t('Your journey is waiting.')?></h1><p class="lead"><?=t('Sign in to continue with your saved profile, assessments and pathway.')?></p></section><section class="auth-card"><div class="step-label">→ &nbsp; <?=t('SIGN IN')?></div><?php if($error):?><div class="error-box" role="alert"><?=htmlspecialchars($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>"><label><?=t('Email')?><input type="email" name="email" required autocomplete="email" value="<?=htmlspecialchars($_POST['email']??'')?>"></label><label><?=t('Password')?><input type="password" name="password" required autocomplete="current-password"></label><button class="primary-btn" type="submit"><?=t('Continue My Journey')?> →</button></form><div class="demo-note">🔐 <?=t('Credentials are checked against the Khetha MySQL database. Passwords are never stored as plain text.')?></div><p class="auth-switch"><?=t('New to Khetha?')?> <a href="register.php"><?=t('Create your profile')?></a></p></section></main></body></html>
