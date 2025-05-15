<?php

use src\Controllers\HomeController;
use src\Controllers\UserController;
use src\Services\ConfigRouter;

$homeController = new HomeController();
$usersController = new UserController();

$route = $_SERVER['REDIRECT_URL'] ?? '/';
$method = ConfigRouter::getMethod();
// var_dump($_SERVER);

switch ($route) {

    case HOME_URL:
        $homeController->displayHomepage();
        break;
        //TODO EDIT THE EXAMPLE FOR POST AND GET METHOD
    // case HOME_URL . 'signIn':
    //     if ($method === 'POST') {
    //         $usersController->treatmentSignIn();
    //     } else {
    //         if (isset($_SESSION['connected']) && ConfigRouter::checkConnection()) {
    //             $usersController->displayDashboard();
    //         } else {
    //             $homeController->displayFormSignIn();
    //         }
    //     }
    //     break;

    // case HOME_URL . 'dashboard':
    //     if (isset($_SESSION['connected']) && ConfigRouter::checkConnection()) {
    //         // $usersController->displayDashboard();
    //     } else {
    //         $homeController->displayHomepage();
    //     }
    //     break;

    case HOME_URL . '403':
        $homeController->page403();
        break;
    case HOME_URL . '404':
        $homeController->page404();
        break;
    default:
        $homeController->page404();
        break;
}
