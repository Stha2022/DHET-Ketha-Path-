<?php
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../includes/journey.php';
$kp_name = $_SESSION['user']['name'] ?? t('there');
$kp_initial = strtoupper(substr($kp_name, 0, 1));
$kp_unread = !empty($_SESSION['user']['id']) ? kp_unread_notification_count((int)$_SESSION['user']['id']) : 0;
?>
<?php include_once __DIR__ . '/bootstrap-css.php'; ?>
<style>
.kp-navbar{min-height:72px;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid var(--line,#dbe7ea);padding:0 4%;position:sticky;top:0;z-index:1000}
.kp-brand-lockup{display:flex;align-items:center;gap:13px}.kp-navbar .kp-brand img{display:block;width:124px;height:auto}.kp-gov-divider{width:1px;height:34px;background:#dbe7ea}.kp-gov-logo{width:112px;height:42px;object-fit:contain;object-position:left center}.kp-navbar .kp-brand{color:var(--ink,#172b4d)}
.kp-profile-btn{border:1px solid var(--line,#dbe7ea);background:#fff;border-radius:999px;padding:6px 14px 6px 6px}.kp-profile-btn:hover,.kp-profile-btn:focus{background:var(--mint,#e7f8f5);border-color:var(--teal,#00a99d)}.kp-profile-btn:after{margin-left:10px}.kp-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#102a43,#00a99d);color:#fff;font-weight:800;font-size:13px;display:grid;place-items:center}.kp-profile-name{font-size:13px;font-weight:700;color:var(--ink,#172b4d)}
.kp-dropdown-menu{border:1px solid var(--line,#dbe7ea);border-radius:14px;box-shadow:0 18px 50px rgba(16,42,67,.12);padding:8px;min-width:200px}.kp-dropdown-menu .dropdown-header{font-size:11px;font-weight:800;letter-spacing:.5px;color:var(--muted,#64748b);text-transform:uppercase}.kp-dropdown-menu .dropdown-item{border-radius:8px;padding:9px 12px;font-size:13px;font-weight:600;color:var(--ink,#172b4d)}.kp-dropdown-menu .dropdown-item:hover,.kp-dropdown-menu .dropdown-item:focus{background:var(--mint,#e7f8f5);color:#087c73}
.kp-mobile-nav{display:none}
@media(max-width:600px){.kp-navbar{min-height:64px;padding:0 12px}.kp-navbar .kp-brand img{width:100px}.kp-gov-divider{height:28px}.kp-gov-logo{width:82px;height:34px}.kp-profile-name{display:none}.kp-profile-btn{padding:5px}.kp-mobile-nav{display:flex;position:fixed;left:10px;right:10px;bottom:10px;z-index:1200;background:rgba(255,255,255,.95);backdrop-filter:blur(15px);border:1px solid var(--line);border-radius:20px;box-shadow:0 14px 40px rgba(16,42,67,.16);padding:7px;justify-content:space-around}.kp-mobile-nav a{font-size:10px;font-weight:800;color:#64748b;text-align:center;padding:7px 10px;border-radius:14px}.kp-mobile-nav a:hover{background:var(--mint);color:var(--teal)}.kp-mobile-nav span{display:block;font-size:18px;line-height:1;margin-bottom:3px}}
</style>
<a class="skip-link" href="#main-content">Skip to main content</a>
<nav class="navbar kp-navbar d-flex align-items-center justify-content-between">
  <a class="navbar-brand kp-brand kp-brand-lockup m-0" href="dashboard.php" aria-label="Khetha">
    <img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha">
    <span class="kp-gov-divider" aria-hidden="true"></span>
    <img class="kp-gov-logo" src="assets/images/dhet-official-logo.png" alt="Department of Higher Education and Training">
  </a>
  <div class="d-flex align-items-center gap-2">
    <a class="kp-bell" href="notifications.php" aria-label="Notifications<?= $kp_unread ? ' — '.$kp_unread.' unread' : '' ?>" title="Notifications">🔔<?php if($kp_unread): ?><b><?= (int)$kp_unread ?></b><?php endif; ?></a>
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="langBtn" aria-label="<?= t('Language') ?>"><span><?= KP_LANGS[kp_lang()]['label'] ?></span></button>
      <ul class="dropdown-menu dropdown-menu-end kp-dropdown-menu">
        <?php foreach (KP_LANGS as $code => $l): ?><li><a class="dropdown-item<?= $code === kp_lang() ? ' active' : '' ?>" href="<?= htmlspecialchars(kp_lang_url($code)) ?>" lang="<?= $code ?>" hreflang="<?= $code ?>"><?= $l['name'] ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="dropdown">
      <button class="btn kp-profile-btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="kp-avatar"><?= htmlspecialchars($kp_initial) ?></span><span class="kp-profile-name"><?= htmlspecialchars($kp_name) ?></span></button>
      <ul class="dropdown-menu dropdown-menu-end kp-dropdown-menu">
        <li><h6 class="dropdown-header"><?= htmlspecialchars($kp_name) ?></h6></li>
        <li><a class="dropdown-item" href="my-profile.php"><?= t('My Profile') ?></a></li>
        <li><a class="dropdown-item" href="cv.php"><?= t('My CV') ?></a></li>
        <li><a class="dropdown-item" href="account.php"><?= t('Account & security') ?></a></li>
        <li><a class="dropdown-item" href="settings.php"><?= t('Settings') ?></a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="logout.php"><?= t('Log out') ?></a></li>
      </ul>
    </div>
  </div>
</nav>
<div class="kp-mobile-nav" aria-label="Quick navigation">
  <a href="dashboard.php"><span>⌂</span>Home</a><a href="directories.php"><span>🧭</span>Explore</a><a href="my-path.php"><span>🛣️</span>My Path</a><a href="ask.php"><span>✨</span>Ask</a>
</div>
<?php include_once __DIR__ . '/bootstrap-js.php'; ?>
