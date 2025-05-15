<?php
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 1);

define('SERVER_NAME', "YOUR_SERVER_NAME_LIKE: feras.fr");

// Check if the server is running on the production server  (feras.fr)  and define the necessary configurations.  Otherwise, use the local development server configurations.  Also, define the constants for the domain name, home URL, and the mail connection details.  Finally, set the error reporting level to E_ALL, and display errors in the development environment.  Finally, define the slug constant.  Note: Replace the placeholders with your actual database connection details and email configuration.
if (strpos($_SERVER["DOCUMENT_ROOT"], SERVER_NAME) !== false) {
    define('IS_PROD', TRUE);
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
