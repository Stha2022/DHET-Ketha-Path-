<?php
// Plain-link language picker for pages that don't load Bootstrap (landing,
// sign in, register). Signed-in pages use the dropdown in navbar.php.
require_once __DIR__ . '/lang.php';
?>
<nav class="lang-links" aria-label="<?= t('Language') ?>">
  <?php foreach (KP_LANGS as $code => $l): ?>
    <a href="<?= htmlspecialchars(kp_lang_url($code)) ?>" lang="<?= $code ?>" hreflang="<?= $code ?>" title="<?= $l['name'] ?>"<?= $code === kp_lang() ? ' aria-current="true" class="active"' : '' ?>><?= $l['label'] ?></a>
  <?php endforeach; ?>
</nav>
