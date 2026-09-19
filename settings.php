<?php
// Settings: things that are about the app on this device (notifications,
// language, data use, offline) and about the learner's data (privacy).
// Profile details (name, grade, subjects) are a separate page.
//
// Demo mode: like the rest of the app there is no database, so the learner's
// data is the PHP session plus this browser's storage. Device preferences
// are kept in localStorage under "khetha-settings" (see assets/js/settings.js).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/assets/lang.php';
require_once __DIR__ . '/includes/journey.php';
require_once __DIR__ . '/includes/csrf.php';
kp_require_auth();

$u = $_SESSION['user'];
$userId = kp_user_id();
$notificationPrefs = kp_notification_preferences($userId);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

// Where privacy requests go. Leave blank to point learners at the contact
// page instead; set it to a monitored address before launch.
$privacyEmail = '';
$policyUpdated = '18 September 2026';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit(t('Your session expired. Go back, refresh the page and try again.'));
    }
    $action = $_POST['action'] ?? '';

    // POST (not GET) so the service worker never caches a copy of personal data.
    if ($action === 'export') {
        $data = [
            'exported_at' => gmdate('c'),
            'email' => $u['email'] ?? null,
            // Everything Khetha holds about the learner: grade, languages, Maths
            // track, subjects, marks, interests, Career Choice result, intended
            // careers and Job Fit results.
            'profile' => profile_get(),
            'language' => kp_lang(),
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="khetha-my-data.json"');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'save_notification_preferences') {
        kp_save_notification_preferences($userId, [
            'pushEnabled' => isset($_POST['pushEnabled']),
            'deadlineReminders' => isset($_POST['deadlineReminders']),
            'assessmentReminders' => isset($_POST['assessmentReminders']),
            'journeyTips' => isset($_POST['journeyTips']),
        ]);
        $_SESSION['settings_flash'] = t('Notification preferences saved.');
        header('Location: settings.php#notifications'); exit;
    }

    if ($action === 'delete') {
        $db = kp_db();
        if ($db) {
            $stmt = @$db->prepare('DELETE FROM users WHERE userID=?');
            if ($stmt) { $stmt->bind_param('i',$userId); $stmt->execute(); $stmt->close(); }
        }
        header('Location: logout.php'); exit;
    }
}

$settingsFlash = $_SESSION['settings_flash'] ?? ''; unset($_SESSION['settings_flash']);
$name = $u['name'] ?? t('Learner');
$email = $u['email'] ?? '';

$notifyTypes = [
    'notifyDeadlines'   => ['icon' => 'bi-calendar-event', 'title' => t('Application deadlines'), 'desc' => t('Reminders before applications, bursaries and NSFAS close.')],
    'notifyAssessments' => ['icon' => 'bi-clipboard-check', 'title' => t('Assessment reminders'), 'desc' => t('A nudge to finish Subject Chooser, Career Choice and Job Fit.')],
    'notifyTips'        => ['icon' => 'bi-lightbulb', 'title' => t('Tips and new careers'), 'desc' => t('Occasional suggestions based on your subjects and interests.')],
];

$policy = [
    [t('Who this policy is from'), [
        t('Khetha Path helps learners explore subjects, careers and study routes. This policy explains what personal information we handle when you use the app, why, and the choices you have. We follow South Africa’s Protection of Personal Information Act (POPIA).'),
    ]],
    [t('What we collect'), [
        t('Information you give us: your name, email address, grade, subjects, interests, and your answers and results in Subject Chooser, Career Choice and Job Fit.'),
        t('Information on your device: your language and the settings on this page. These stay in your browser and are not sent to us.'),
        t('We do not ask for your ID number, address or marks, and we do not collect your location.'),
    ]],
    [t('Why we use it'), [
        t('To personalise your journey around your level, subjects and interests, to show you relevant careers and qualifications, and to remember your progress. We do not use your information for anything unrelated to that.'),
    ]],
    [t('Who can see it'), [
        t('We do not sell your information or use it for advertising. A career practitioner only sees what you choose to send when you contact an advisor.'),
        t('In this version, Ask Khetha answers from built-in guidance and your questions are not passed to an outside AI provider. If that changes, this policy will say so before it does.'),
    ]],
    [t('Learners under 18'), [
        t('Most learners using Khetha are under 18. If you are, please make sure a parent or guardian knows you are using the app and agrees to it, because the law requires their consent for children’s information.'),
    ]],
    [t('How long we keep it'), [
        t('We keep your information while you have a profile. When you delete your data, it is removed. You can do this at any time from this page.'),
    ]],
    [t('How we protect it'), [
        t('Passwords are never shown back to you, connections use HTTPS, and signing out clears the pages saved on your device so the next person on a shared phone cannot see yours.'),
    ]],
    [t('Your rights'), [
        t('You can ask to see the information we hold about you, correct it, delete it, object to how it is used, or withdraw your consent. You can download a copy of your data or delete it yourself from this page.'),
        t('If you are unhappy with how your information is handled, you can complain to the Information Regulator at inforegulator.org.za.'),
    ]],
    [t('Changes to this policy'), [
        t('If we change what we collect or how we use it, we will update this page and the date below.'),
    ]],
];
?>
<!DOCTYPE html>
<html lang="<?= kp_lang() ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php include __DIR__ . "/assets/pwa-head.php"; ?>
        <title><?= t('Settings') ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .settings-jump{display:flex;gap:8px;overflow-x:auto;padding:2px 0 6px;margin:0 0 22px;scrollbar-width:none}
            .settings-jump::-webkit-scrollbar{display:none}
            .settings-jump a,.lang-choice a{text-decoration:none}
            .settings-jump a{flex:none;border:1px solid var(--line);background:#fff;border-radius:999px;padding:8px 14px;font-size:13px;font-weight:700;color:var(--ink)}
            .settings-jump a:hover,.settings-jump a:focus-visible{background:var(--mint);border-color:var(--teal);color:#087c73}
            .settings-section{margin-bottom:20px;scroll-margin-top:16px}
            .settings-section>h2{display:flex;align-items:center;gap:10px;font-size:19px;margin:0 0 4px}
            .settings-section>h2 i{color:var(--teal)}
            .settings-lede{color:var(--muted);font-size:13px;line-height:1.55;margin:0 0 6px}
            .settings-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:14px 0;border-top:1px solid var(--line)}
            .settings-row:first-of-type{border-top:0}
            .settings-row .form-check{margin:0;padding:0;min-height:0;display:flex;align-items:center}
            .settings-row .form-check-input{width:2.6em;height:1.4em;margin:0;float:none;cursor:pointer}
            .settings-row .form-check-input:disabled{cursor:not-allowed}
            .settings-row-copy{display:flex;gap:12px;align-items:flex-start;min-width:0}
            .settings-row-copy>i{color:var(--muted);font-size:18px;margin-top:1px}
            .settings-row-copy b{display:block;font-size:14px}
            .settings-row-copy span{display:block;color:var(--muted);font-size:12.5px;line-height:1.5;margin-top:2px}
            .settings-sub{opacity:.55;transition:opacity .2s}
            .settings-sub.on{opacity:1}
            .settings-status{font-size:13px;line-height:1.5;margin:10px 0 0}
            .settings-status:empty{display:none}
            .settings-note{background:#fff7df;color:#80651c;border-radius:12px;padding:11px 14px;font-size:12.5px;line-height:1.5;margin-top:12px}
            .lang-choice{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
            .lang-choice a{border:1px solid var(--line);background:#fff;border-radius:12px;padding:10px 16px;font-size:14px;font-weight:700}
            .lang-choice a:hover{border-color:var(--teal)}
            .lang-choice a[aria-current]{background:var(--mint);border-color:var(--teal);color:#087c73}
            .settings-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
            .settings-actions .btn,.settings-actions button{font-size:13px;font-weight:700;border-radius:12px;padding:10px 16px}
            .settings-section .accordion{--bs-accordion-border-radius:14px;--bs-accordion-inner-border-radius:13px;--bs-accordion-border-color:var(--line);margin-top:14px}
            .settings-section .accordion-button{font-size:14px;font-weight:700}
            .settings-section .accordion-body{font-size:13.5px;line-height:1.65;color:#3d5168}
            .settings-section .accordion-body p:last-child{margin-bottom:0}
            .settings-danger{border-color:#f2cccc}
            .settings-danger>h2 i{color:#c0392b}
            @media(max-width:600px){.dashboard.narrow{padding:30px 16px}.settings-row{align-items:flex-start}}
        </style>
    </head>
    <body>
        <?php include __DIR__ . '/assets/navbar.php'; ?>

        <main id="main-content" class="dashboard narrow">
            <p class="eyebrow"><?= t('YOUR APP') ?></p>
            <h1><?= t('Settings') ?></h1>
            <p class="muted mb-4"><?= t('Control notifications, language, data use and your privacy. Your name, grade and subjects live in My Profile.') ?></p>

            <nav class="settings-jump" aria-label="<?= t('Jump to') ?>">
                <a href="#notifications"><?= t('Notifications') ?></a>
                <a href="#language"><?= t('Language') ?></a>
                <a href="#data"><?= t('Data &amp; offline') ?></a>
                <a href="#privacy"><?= t('Privacy') ?></a>
                <a href="#account"><?= t('Account') ?></a>
            </nav>

            <!-- Notifications -->
            <section class="panel settings-section" id="notifications" aria-labelledby="h-notifications">
                <h2 id="h-notifications"><i class="bi bi-bell"></i> <?= t('Notifications') ?></h2>
                <p class="settings-lede"><?= t('Khetha can remind you about things that matter, like closing dates. Your browser asks for permission first, and you choose what you get.') ?></p>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <i class="bi bi-bell-fill"></i>
                        <div>
                            <b id="notify-label"><?= t('Allow notifications') ?></b>
                            <span id="notify-perm"></span>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="notify" aria-labelledby="notify-label" data-setting="notify" <?= $notificationPrefs['pushEnabled'] ? 'checked' : '' ?>>
                    </div>
                </div>

                <div class="settings-sub" id="notify-types">
                    <?php foreach ($notifyTypes as $key => $n): ?>
                        <div class="settings-row">
                            <div class="settings-row-copy">
                                <i class="bi <?= $n['icon'] ?>"></i>
                                <div>
                                    <b id="l-<?= $key ?>"><?= $n['title'] ?></b>
                                    <span><?= $n['desc'] ?></span>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="<?= $key ?>" aria-labelledby="l-<?= $key ?>" data-setting="<?= $key ?>" data-needs-notify>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="settings-actions">
                    <button type="button" class="btn btn-outline-primary" id="notify-test" hidden><i class="bi bi-send"></i> <?= t('Send a test notification') ?></button>
                </div>
                <p class="settings-status" id="notify-status" role="status" aria-live="polite"></p>
                <div class="settings-note" id="notify-ios" hidden><?= t('On iPhone and iPad, notifications only work once Khetha is added to your Home Screen: tap Share, then Add to Home Screen, and open it from there.') ?></div>
                <form method="post" class="settings-actions" id="notificationPrefsForm">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="save_notification_preferences">
                    <input type="hidden" name="pushEnabled" value="1" id="pushEnabledField">
                    <input type="hidden" name="deadlineReminders" value="1" id="deadlineField">
                    <input type="hidden" name="assessmentReminders" value="1" id="assessmentField">
                    <input type="hidden" name="journeyTips" value="1" id="tipsField">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-cloud-check"></i> <?= t('Save notification preferences') ?></button>
                </form>
                <?php if ($settingsFlash): ?><p class="settings-status text-success" role="status"><?= htmlspecialchars($settingsFlash) ?></p><?php endif; ?>
                <p class="settings-lede mt-2 mb-0"><?= t('Your preferences are stored with your account. Device permission is separate: the browser or native app must also allow notifications.') ?></p>
            </section>

            <!-- Language -->
            <section class="panel settings-section" id="language" aria-labelledby="h-language">
                <h2 id="h-language"><i class="bi bi-translate"></i> <?= t('Language') ?></h2>
                <p class="settings-lede"><?= t('Pick the language you are most comfortable reading. You can change it any time.') ?></p>
                <div class="lang-choice">
                    <?php foreach (KP_LANGS as $code => $l): ?>
                        <a href="<?= htmlspecialchars(kp_lang_url($code)) ?>" lang="<?= $code ?>" hreflang="<?= $code ?>"<?= $code === kp_lang() ? ' aria-current="true"' : '' ?>><?= $l['name'] ?></a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Data & offline -->
            <section class="panel settings-section" id="data" aria-labelledby="h-data">
                <h2 id="h-data"><i class="bi bi-cloud-arrow-down"></i> <?= t('Data &amp; offline') ?></h2>
                <p class="settings-lede"><?= t('Save data on a small bundle or a slow connection, and keep your pages available without a signal.') ?></p>

                <div class="settings-row">
                    <div class="settings-row-copy">
                        <i class="bi bi-speedometer2"></i>
                        <div>
                            <b id="l-dataSaver"><?= t('Data saver') ?></b>
                            <span><?= t('Stops the greeting video from loading and stops Khetha saving pages in the background.') ?></span>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="dataSaver" aria-labelledby="l-dataSaver" data-setting="dataSaver">
                    </div>
                </div>

                <div class="settings-actions">
                    <button type="button" class="btn btn-outline-primary" id="offline-save"><i class="bi bi-download"></i> <?= t('Save pages for offline') ?></button>
                    <button type="button" class="btn btn-outline-secondary" id="offline-clear"><i class="bi bi-trash3"></i> <?= t('Clear saved pages') ?></button>
                </div>
                <p class="settings-status" id="offline-status" role="status" aria-live="polite"></p>
            </section>

            <!-- Privacy -->
            <section class="panel settings-section" id="privacy" aria-labelledby="h-privacy">
                <h2 id="h-privacy"><i class="bi bi-shield-check"></i> <?= t('Privacy') ?></h2>
                <p class="settings-lede"><?= t('Your information is used to personalise your journey and nothing else. We never sell it.') ?></p>

                <div class="accordion" id="policy">
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#policy-body" aria-expanded="false" aria-controls="policy-body" id="policy-toggle">
                                <i class="bi bi-file-earmark-text me-2"></i> <?= t('Privacy policy') ?>
                            </button>
                        </h3>
                        <div id="policy-body" class="accordion-collapse collapse" data-bs-parent="#policy">
                            <div class="accordion-body">
                                <p class="text-muted small"><?= t('Last updated {date}', ['date' => t($policyUpdated)]) ?></p>
                                <?php foreach ($policy as [$heading, $paras]): ?>
                                    <h4 class="h6 fw-bold mt-3"><?= $heading ?></h4>
                                    <?php foreach ($paras as $p): ?><p><?= $p ?></p><?php endforeach; ?>
                                <?php endforeach; ?>
                                <h4 class="h6 fw-bold mt-3"><?= t('Contact us about your information') ?></h4>
                                <p>
                                    <?php if ($privacyEmail !== ''): ?>
                                        <?= t('Write to us at') ?> <a href="mailto:<?= htmlspecialchars($privacyEmail) ?>"><?= htmlspecialchars($privacyEmail) ?></a>.
                                    <?php else: ?>
                                        <?= t('Send us a message through') ?> <a href="contact-advisor.php"><?= t('Contact an advisor') ?></a>.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-actions">
                    <form method="post" action="settings.php">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="action" value="export">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-box-arrow-down"></i> <?= t('Download my data') ?></button>
                    </form>
                </div>
                <p class="settings-lede mt-2 mb-0"><?= t('A copy of your profile and results as a file you can keep.') ?></p>
            </section>

            <!-- Account -->
            <section class="panel settings-section" id="account" aria-labelledby="h-account">
                <h2 id="h-account"><i class="bi bi-person-circle"></i> <?= t('Account') ?></h2>
                <div class="settings-row">
                    <div class="settings-row-copy">
                        <i class="bi bi-person"></i>
                        <div>
                            <b><?= htmlspecialchars($name) ?></b>
                            <?php if ($email): ?><span><?= htmlspecialchars($email) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="logout.php"><?= t('Log out') ?></a>
                </div>
            </section>

            <section class="panel settings-section settings-danger" id="delete" aria-labelledby="h-delete">
                <h2 id="h-delete"><i class="bi bi-exclamation-triangle"></i> <?= t('Delete my data') ?></h2>
                <p class="settings-lede"><?= t('Removes your profile, results and saved choices, clears what Khetha saved on this device, and signs you out. This cannot be undone.') ?></p>
                <div class="settings-actions">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><?= t('Delete my data') ?></button>
                </div>
            </section>

            <p class="text-center text-muted small mt-4"><?= t('Khetha Path') ?> &middot; <a href="#privacy" data-open-policy><?= t('Privacy policy') ?></a></p>
        </main>

        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="post" action="settings.php" id="delete-form" style="border-radius:20px">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <div class="modal-header border-0 pb-0">
                        <h2 class="modal-title h5" id="deleteTitle"><?= t('Delete all your data?') ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('Close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0"><?= t('Your profile, assessment results and saved choices will be removed and you will be signed out. You can start again by creating a new profile.') ?></p>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= t('Keep my data') ?></button>
                        <button type="submit" class="btn btn-danger"><?= t('Yes, delete it') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script type="application/json" id="settings-i18n"><?= json_encode([
            'permDefault'   => t('Not asked yet. Turn this on and your browser will ask you.'),
            'permGranted'   => t('Allowed in your browser.'),
            'permDenied'    => t('Blocked in your browser. To allow it, open your browser’s site settings for Khetha and set Notifications to Allow, then come back.'),
            'permOff'       => t('Switched off in Khetha.'),
            'permNone'      => t('This browser does not support notifications.'),
            'testTitle'     => t('Khetha'),
            'testBody'      => t('Notifications are working. We will remind you about the things you chose.'),
            'testSent'      => t('Test notification sent.'),
            'testFailed'    => t('Could not show a notification. Check that notifications are allowed for this site.'),
            'saving'        => t('Saving pages…'),
            'saved'         => t('Done. Your pages are saved for offline use.'),
            'saveOffline'   => t('You are offline, so nothing could be saved. Try again with a connection.'),
            'saveNoSw'      => t('Offline saving is not available in this browser.'),
            'cleared'       => t('Saved pages cleared. They will be saved again the next time you open your dashboard.'),
            'clearedNone'   => t('There were no saved pages to clear.'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
        <script>
(function(){
  var map={pushEnabledField:'notify',deadlineField:'notifyDeadlines',assessmentField:'notifyAssessments',tipsField:'notifyTips'};
  var f=document.getElementById('notificationPrefsForm'); if(!f)return;
  f.addEventListener('submit',function(){Object.keys(map).forEach(function(k){var x=document.getElementById(k),c=document.getElementById(map[k]);x.disabled=!c.checked;});});
})();
</script>
<script>window.KP_SERVER_SETTINGS = <?= json_encode($notificationPrefs, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/js/settings.js"></script>
    </body>
</html>
