<?php

/**
 * Application Bootstrap
 *
 * Execution order:
 *   1. Composer autoloader
 *   2. Environment configuration (.env)
 *   3. Error & exception handling
 *   4. Session initialisation (secure)
 *   5. Security headers
 *   6. Request logging
 *   7. Route dispatch
 */

// -----------------------------------------------------------------------
// 1. Autoloader & Configuration
// -----------------------------------------------------------------------
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Config;
use App\Services\Logger;
use App\Middleware\SecurityHeaders;
use App\Middleware\RequestLogger;
use App\Controllers\HomeController;

Config::boot();

// -----------------------------------------------------------------------
// 2. Error Reporting (environment-aware)
// -----------------------------------------------------------------------
if (Config::isProduction()) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/php-errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

date_default_timezone_set(Config::get('APP_TIMEZONE', 'Europe/Paris'));

// -----------------------------------------------------------------------
// 3. Global Exception Handler
// -----------------------------------------------------------------------
set_exception_handler(function (\Throwable $e): void {
    http_response_code(500);

    // Log the full exception (never expose to users).
    try {
        Logger::channel('app')->error('Uncaught exception', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]);
    } catch (\Throwable) {
        // Logger itself failed — fall back to error_log.
        error_log('FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    }

    if (Config::isProduction()) {
        // Show a clean 500 page — never raw stack traces.
        include __DIR__ . '/Views/home/500.php';
    } else {
        echo '<pre style="color:red;background:#fff;padding:1em;font-size:.9em">'
            . htmlspecialchars((string) $e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</pre>';
    }
    exit;
});

// -----------------------------------------------------------------------
// 4. Secure Session Initialisation
// -----------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_samesite',  'Strict');
    ini_set('session.gc_maxlifetime',  (string) Config::getInt('SESSION_LIFETIME', 7200));

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => Config::isProduction(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    // --- Idle Timeout ---
    $idleTimeout = Config::getInt('SESSION_IDLE_TIMEOUT', 1800);
    if (isset($_SESSION['_last_activity'])) {
        if (time() - $_SESSION['_last_activity'] > $idleTimeout) {
            // Session has been idle too long — destroy it.
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['error'] = 'Your session expired due to inactivity. Please log in again.';
        }
    }
    $_SESSION['_last_activity'] = time();

    // --- Session Regeneration Tracking ---
    // Regenerate the session ID periodically to mitigate fixation.
    $regenInterval = 300; // every 5 minutes
    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = time();
    } elseif (time() - $_SESSION['_created_at'] > $regenInterval) {
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
    }
}

// -----------------------------------------------------------------------
// 5. Security Headers
// -----------------------------------------------------------------------
SecurityHeaders::send();

// -----------------------------------------------------------------------
// 6. Request Logging (non-blocking for static assets)
// -----------------------------------------------------------------------
RequestLogger::log();

// -----------------------------------------------------------------------
// 7. Route Dispatch
// -----------------------------------------------------------------------
require_once __DIR__ . '/Services/router.php';
