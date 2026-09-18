<?php
// Account & security: change the name, the email address or the password.
// All of the rules live in includes/account.php; this page only renders results.
session_start();
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/account.php';
require_once __DIR__ . '/includes/csrf.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

// Nothing on this page should sit in a browser or proxy cache, or be saved for
// offline use by the service worker.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$userId = (int)($_SESSION['user']['id'] ?? 0);
$errors = [];
$done = '';
$openForm = '';   // which section to re-open after a failed submit

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $openForm = $action;

    if (!csrf_check($_POST['csrf'] ?? '')) {
        $errors[$action] = t('Your session expired. Refresh the page and try again.');
    } else {
        $r = ['ok' => false, 'error' => t('Unknown request.')];
        if ($action === 'name') {
            $r = account_change_name($userId, (string)($_POST['name'] ?? ''));
            $msg = t('Your name has been updated.');
        } elseif ($action === 'email') {
            $r = account_change_email($userId, (string)($_POST['email'] ?? ''), (string)($_POST['current_password'] ?? ''));
            $msg = t('Your email address has been updated.');
        } elseif ($action === 'password') {
            $r = account_change_password($userId, (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
            $msg = t('Your password has been changed.');
        }
        if ($r['ok']) {
            // Redirect after a successful POST so a refresh can't resubmit, and so
            // the typed passwords leave the browser's resend buffer.
            $_SESSION['account_flash'] = $msg;
            header('Location: account.php');
            exit;
        }
        $errors[$action] = $r['error'];
    }
}

if (!empty($_SESSION['account_flash'])) { $done = $_SESSION['account_flash']; unset($_SESSION['account_flash']); }

$account = account_get($userId);
$hasPassword = account_has_password($userId);
$e = fn($v) => htmlspecialchars((string)$v);
// After a failed attempt, show what they typed again — except passwords, which are never echoed back.
$old = fn(string $k, string $fallback = '') => $e($errors ? ($_POST[$k] ?? $fallback) : $fallback);
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <meta name="robots" content="noindex">
        <title><?= t('Account & security') ?> — Khetha</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <div class="container py-4" style="max-width:720px">
            <div class="mb-4">
                <h2 class="mb-1"><?= t('Account & security') ?></h2>
                <p class="text-muted mb-0"><?= t('Change your name, email address or password. To change your email or password we ask for your current password first, so nobody else using your phone can take over your account.') ?></p>
            </div>

            <?php if ($done): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle-fill me-1"></i><?= $e($done) ?></div><?php endif; ?>

            <?php if (!$hasPassword): ?>
                <div class="alert alert-warning py-2 small"><?= t('You signed in before this feature existed, so there is no password on file for this session. Sign out and in again to set one.') ?></div>
            <?php endif; ?>

            <!-- Name -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= t('Name') ?></h5>
                    <p class="text-muted small"><?= t('This is the name Khetha greets you with, and the name on your CV.') ?></p>
                    <?php if (!empty($errors['name'])): ?><div class="alert alert-danger py-2 small"><?= $e($errors['name']) ?></div><?php endif; ?>
                    <form method="post" action="account.php" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="name">
                        <label class="form-label small fw-bold" for="name"><?= t('Full name') ?></label>
                        <input class="form-control mb-3" id="name" name="name" maxlength="120" required autocomplete="name" value="<?= $old('name', $account['name']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><?= t('Save name') ?></button>
                    </form>
                </div>
            </div>

            <!-- Email -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= t('Email address') ?></h5>
                    <p class="text-muted small"><?= t('You sign in with this address.') ?> <strong><?= $e($account['email']) ?></strong></p>
                    <?php if (!empty($errors['email'])): ?><div class="alert alert-danger py-2 small"><?= $e($errors['email']) ?></div><?php endif; ?>
                    <form method="post" action="account.php" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="email">
                        <label class="form-label small fw-bold" for="email"><?= t('New email address') ?></label>
                        <input class="form-control mb-3" type="email" id="email" name="email" maxlength="190" required autocomplete="email" value="<?= $old('email') ?>">
                        <label class="form-label small fw-bold" for="email_current"><?= t('Your current password') ?></label>
                        <input class="form-control mb-3" type="password" id="email_current" name="current_password" required autocomplete="current-password">
                        <button type="submit" class="btn btn-primary btn-sm" <?= $hasPassword ? '' : 'disabled' ?>><?= t('Change email') ?></button>
                    </form>
                </div>
            </div>

            <!-- Password -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title"><?= t('Password') ?></h5>
                    <p class="text-muted small"><?= t('Use at least {n} characters. Changing your password signs out any other device using this account.', ['n' => ACCOUNT_MIN_PASSWORD]) ?></p>
                    <?php if (!empty($errors['password'])): ?><div class="alert alert-danger py-2 small"><?= $e($errors['password']) ?></div><?php endif; ?>
                    <form method="post" action="account.php" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= $e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="password">
                        <label class="form-label small fw-bold" for="pw_current"><?= t('Your current password') ?></label>
                        <input class="form-control mb-3" type="password" id="pw_current" name="current_password" required autocomplete="current-password">
                        <label class="form-label small fw-bold" for="pw_new"><?= t('New password') ?></label>
                        <input class="form-control mb-3" type="password" id="pw_new" name="new_password" minlength="<?= ACCOUNT_MIN_PASSWORD ?>" required autocomplete="new-password">
                        <label class="form-label small fw-bold" for="pw_confirm"><?= t('Confirm new password') ?></label>
                        <input class="form-control mb-3" type="password" id="pw_confirm" name="confirm_password" minlength="<?= ACCOUNT_MIN_PASSWORD ?>" required autocomplete="new-password">
                        <button type="submit" class="btn btn-primary btn-sm" <?= $hasPassword ? '' : 'disabled' ?>><?= t('Change password') ?></button>
                    </form>
                </div>
            </div>

            <p class="text-muted small"><?= t('Looking for your grade, subjects or interests?') ?> <a href="my-profile.php"><?= t('My Profile') ?></a></p>
        </div>

        <?php if ($openForm): ?>
        <script>
        (function () {
            // Bring the section that failed back into view.
            var el = document.querySelector('.alert-danger');
            if (el) el.scrollIntoView({ block: 'center' });
        })();
        </script>
        <?php endif; ?>
    </body>
</html>
