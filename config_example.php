<?php
define('SERVER_NAME', "YOUR_SERVER_NAME_LIKE: feras.fr");
define('ENCRYPTION_KEY', "YOUR_ENCRYPTION_KEY");    // 32 characters

// Detect the environment from DOCUMENT_ROOT and set all environment-specific constants.
if (strpos($_SERVER["DOCUMENT_ROOT"], SERVER_NAME) !== false) {
    define('IS_PROD', TRUE);
    // Production: never expose errors to the browser.
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // Database connection
    define("DB_HOST", "");
    define("DB_PORT", "");
    define("DB_USER", "");
    define("DB_PWD", "");
    define("DB_NAME", "");
    // DOMAIN name , Home url 
    define("DOMAIN", "https://yoursite.com");
    define("HOME_URL", "/");
    // Mail connection
    define('HOST', '');
    define('PORT', '');
    define('USERNAME', '');
    define('PASSWORD', '');

    define('FROM_EMAIL', '');
    define('ADMIN_COM', '');
    define('SENDER', '');
} else {
    define('IS_PROD', FALSE);
    // Development: show all errors so issues are caught early.
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    // Database connection
    define("DB_HOST", "");
    define("DB_PORT", "");
    define("DB_USER", "");
    define("DB_PWD", "");
    define("DB_NAME", "");
    // DOMAIN name , Home url 
    define("DOMAIN", "http://localhost_project_name");
    define('HOME_URL', '/');

    // Mail connection
    define('HOST', '');
    define('PORT', '');
    define('USERNAME', '');
    define('PASSWORD', '');
    define('FROM_EMAIL', '');
    define('ADMIN_COM', '');
    define('SENDER', '');
}
