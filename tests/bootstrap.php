<?php

/**
 * PHPUnit Bootstrap.
 *
 * Sets up the autoloader and test environment.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Create a minimal .env for testing if not present.
$testEnvPath = __DIR__ . '/../.env';
if (!file_exists($testEnvPath)) {
    file_put_contents($testEnvPath, implode("\n", [
        'APP_ENV=testing',
        'APP_DEBUG=true',
        'APP_URL=http://localhost',
        'APP_KEY=' . bin2hex(random_bytes(32)),
        'APP_TIMEZONE=Europe/Paris',
        'DB_HOST=127.0.0.1',
        'DB_PORT=3306',
        'DB_NAME=test_db',
        'DB_USER=root',
        'DB_PASS=',
        'SESSION_LIFETIME=7200',
        'SESSION_IDLE_TIMEOUT=1800',
        'LOG_PATH=logs',
        'LOG_LEVEL=debug',
    ]));
}

// Simulate $_SERVER for CLI tests.
$_SERVER['REMOTE_ADDR']   = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
