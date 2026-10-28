<?php
/**
 * Bootstrap file included at the top of every page.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/partials.php';

/* ---------- Errors: log them, never show them to visitors in production ---------- */
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log(sprintf('SkillSwap uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = APP_DEBUG ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Something went wrong · SkillSwap</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:0 16px;color:#171a2c">'
        . '<h1 style="font-size:1.4rem">Something went wrong</h1>'
        . '<p>An unexpected error occurred on our side. Please go back and try again in a moment.</p>'
        . '<p><a href="index.php">Back to SkillSwap</a></p>' . $detail . '</div>';
    exit;
});

/* ---------- Session ---------- */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 7));
    session_name('skillswap_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => APP_ENV === 'production' || is_https(),
    ]);
    session_start();
}

/* ---------- Security headers ---------- */
function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }
    return $nonce;
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    // Scripts only from this site (plus our one inline theme script, via nonce); fonts from Google Fonts.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . csp_nonce() . "'; "
        . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
        . "img-src 'self' data: blob:; connect-src 'self'; object-src 'none'; base-uri 'self'; "
        . "form-action 'self'; frame-ancestors 'none'");
    // Pages can contain account-specific data: keep them out of shared caches.
    header('Cache-Control: private, no-cache');
    if (APP_ENV === 'production' && is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}
