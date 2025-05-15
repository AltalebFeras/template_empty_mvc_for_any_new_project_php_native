<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// https://www.php.net/manual/fr/function.session-status.php
// if(session_status() !== PHP_SESSION_ACTIVE)
// {
//     session_start();
// }
require_once __DIR__ . '/Services/autoload.php';
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/Services/router.php";
