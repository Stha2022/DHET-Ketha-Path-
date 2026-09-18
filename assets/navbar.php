<?php
// Shared top navbar — include once, right after <body> opens, in place of
// a page's own <header class="topbar">...</header>. Include only on pages
// that already guard on $_SESSION['user'] (login.php redirects otherwise).
require_once __DIR__ . '/lang.php';
$kp_name = $_SESSION['user']['name'] ?? t('there');
$kp_initial = strtoupper(substr($kp_name, 0, 1));
?>
<?php include_once __DIR__ . '/bootstrap-css.php'; ?>
<style>
.kp-navbar{height:72px;background:#fff;border-bottom:1px solid var(--line,#dbe7ea);padding:0 5%}
.kp-navbar .kp-brand{font-size:20px;font-weight:600;color:var(--ink,#172b4d)}
.kp-navbar .kp-brand img{display:block;width:130px;height:auto}
.kp-profile-btn{border:1px solid var(--line,#dbe7ea);background:#fff;border-radius:999px;padding:6px 14px 6px 6px}
.kp-profile-btn:hover,.kp-profile-btn:focus{background:var(--mint,#e7f8f5);border-color:var(--teal,#00a99d)}
.kp-profile-btn:after{margin-left:10px}
.kp-avatar{width:34px;height:34px;border-radius:50%;background:var(--navy,#102a43);color:#fff;font-weight:800;font-size:13px;display:grid;place-items:center}
.kp-profile-name{font-size:13px;font-weight:700;color:var(--ink,#172b4d)}
.kp-dropdown-menu{border:1px solid var(--line,#dbe7ea);border-radius:14px;box-shadow:0 18px 50px rgba(16,42,67,.12);padding:8px;min-width:200px}
.kp-dropdown-menu .dropdown-header{font-size:11px;font-weight:800;letter-spacing:.5px;color:var(--muted,#64748b);text-transform:uppercase}
.kp-dropdown-menu .dropdown-item{border-radius:8px;padding:9px 12px;font-size:13px;font-weight:600;color:var(--ink,#172b4d)}
.kp-dropdown-menu .dropdown-item:hover,.kp-dropdown-menu .dropdown-item:focus{background:var(--mint,#e7f8f5);color:#087c73}
.kp-dropdown-menu .dropdown-item.text-danger:hover{background:#fdf1ef}
.kp-dropdown-menu form{margin:0}
.kp-dropdown-menu form .dropdown-item{width:100%;text-align:left;border:0;background:transparent}
@media(max-width:600px){.kp-navbar{height:64px}.kp-profile-name{display:none}.kp-navbar .kp-brand img{width:115px}}
</style>

<nav class="navbar kp-navbar d-flex align-items-center justify-content-between">
  <a class="navbar-brand kp-brand d-flex align-items-center gap-2 m-0" href="dashboard.php">
    <img class="khetha-logo small-logo" src="assets/images/khetha-logo.png" alt="Khetha">
  </a>

  <div class="d-flex align-items-center gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="langBtn" aria-label="<?= t('Language') ?>">
        <span><?= KP_LANGS[kp_lang()]['label'] ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end kp-dropdown-menu">
        <?php foreach (KP_LANGS as $code => $l): ?>
          <li><a class="dropdown-item<?= $code === kp_lang() ? ' active' : '' ?>" href="<?= htmlspecialchars(kp_lang_url($code)) ?>" lang="<?= $code ?>" hreflang="<?= $code ?>"><?= $l['name'] ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="dropdown">
      <button class="btn kp-profile-btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <span class="kp-avatar"><?= htmlspecialchars($kp_initial) ?></span>
        <span class="kp-profile-name"><?= htmlspecialchars($kp_name) ?></span>
      </button>
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

<?php include_once __DIR__ . '/bootstrap-js.php'; ?>
