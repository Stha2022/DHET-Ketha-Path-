<?php
/**
 * Khetha Path — language support.
 *
 * The chosen language lives in the `khetha_lang` cookie (set by
 * set-lang.php), so it works before sign-in and survives logout.
 *
 * Strings are keyed by their English text: wrap a string in t('...') and it
 * is looked up in assets/lang/<code>.php. A missing translation falls back to
 * the English text, so a half-translated page never breaks.
 *
 * To add a language: add it to KP_LANGS and drop an assets/lang/<code>.php
 * that returns an ['English text' => 'Translation'] array.
 */

const KP_LANGS = [
    'en' => ['label' => 'EN', 'name' => 'English'],
    'xh' => ['label' => 'XH', 'name' => 'isiXhosa'],
    'zu' => ['label' => 'ZU', 'name' => 'isiZulu'],
];

function kp_lang(): string {
    static $lang = null;
    if ($lang === null) {
        $c = $_COOKIE['khetha_lang'] ?? 'en';
        $lang = isset(KP_LANGS[$c]) ? $c : 'en';
    }
    return $lang;
}

function kp_dict(): array {
    static $dict = null;
    if ($dict === null) {
        $file = __DIR__ . '/lang/' . kp_lang() . '.php';
        $dict = (kp_lang() !== 'en' && is_file($file)) ? require $file : [];
    }
    return $dict;
}

/**
 * Translate a string. The result is HTML-safe and can be echoed directly,
 * including inside attributes. Strings may hold trusted markup (<b>, &rarr;);
 * {placeholders} are filled from $vars and escaped.
 */
function t(string $text, array $vars = []): string {
    $out = kp_dict()[$text] ?? $text;
    foreach ($vars as $k => $v) {
        $out = str_replace('{' . $k . '}', htmlspecialchars((string)$v), $out);
    }
    return $out;
}

/** URL that switches language, then returns to the current page. */
function kp_lang_url(string $code): string {
    $next = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    if (!empty($_SERVER['QUERY_STRING'])) $next .= '?' . $_SERVER['QUERY_STRING'];
    return 'set-lang.php?lang=' . urlencode($code) . '&next=' . urlencode($next);
}
