<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/Services/autoload.php';
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/Services/router.php";
