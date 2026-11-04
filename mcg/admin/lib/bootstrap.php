<?php
/**
 * Admin bootstrap: session, security headers and shared libraries.
 */

declare(strict_types=1);

require_once __DIR__ . '/core.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = mcg_is_https();
    $cookiePath = mcg_admin_web_path();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_name('mcg_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/auth.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/* Never render PHP errors to the browser in production. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
