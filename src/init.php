<?php
// Config and autoload must come first so constants (IS_PROD, HOME_URL…) are
// available when the session cookie is configured below.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

// -----------------------------------------------------------------------
// Secure session initialisation
// All ini_set() calls must happen BEFORE session_start().
// -----------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode',  '1'); // reject unrecognised session IDs
    ini_set('session.use_only_cookies', '1'); // never accept session ID in the URL
    ini_set('session.cookie_httponly',  '1'); // block JS access to the cookie
    ini_set('session.cookie_samesite',  'Strict'); // block cross-site requests

    session_set_cookie_params([
        'lifetime' => 0,      // cookie lives until the browser closes
        'path'     => '/',
        'secure'   => defined('IS_PROD') && IS_PROD, // HTTPS-only in production
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();
}

date_default_timezone_set('Europe/Paris');

// -----------------------------------------------------------------------
// Global exception handler
// In production: render a clean 500 page instead of a raw stack trace.
// In development: re-throw so the default handler prints the full trace.
// -----------------------------------------------------------------------
set_exception_handler(function (\Throwable $e): void {
    http_response_code(500);
    if (defined('IS_PROD') && IS_PROD) {
        include __DIR__ . '/Views/home/500.php';
    } else {
        echo '<pre style="color:red;background:#fff;padding:1em;font-size:.9em">'
            . htmlspecialchars((string) $e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre>';
    }
    exit;
});

require_once __DIR__ . '/Services/router.php';
