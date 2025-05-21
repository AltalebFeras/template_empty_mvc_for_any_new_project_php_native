<?php

namespace src\Services;

class ConfigRouter
{
    /**
     * Retrieves the HTTP request method.
     *
     * This function returns the request method from the server (e.g., GET, POST).
     * If the request method is POST and a spoofed method is provided in 
     * the `_POST['_method']` field, it overrides the method accordingly.
     *
     * Supported override values: 'GET', 'POST', 'DELETE'.
     *
     * @return string The effective HTTP request method.
     */

    public static function getMethod(): string
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === "POST" && isset($_POST['_method']) && !empty($_POST['_method'])) {
            switch ($_POST['_method']) {
                case 'DELETE':
                case 'POST':
                    $method = "POST";
                    break;
                case 'GET':
                    $method = "GET";
                    break;
                default:
                    //
                    break;
            }
        }

        return $method;
    }
    /**
     * 
     * It is for the purpose of checking the connection of the user in case of the session id stolen by another user "hacker"
     * sadly, to avoid any further actions we have to destroy the session for the both users with that session id
     * because the PHP is limited and it is not cabable to identify which user who created the session  
     * @return bool
     */
    public static function checkOriginConnection(): bool
    {
        if (
            !isset($_SESSION['ip_address'], $_SESSION['user_agent']) ||
            $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '') ||
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
        ) {
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['error'] = 'Votre session est expirée! veuillez vous reconnecter.';
            header('Location: ' . HOME_URL . 'signIn');
            exit();
        }

        return true;
    }
}
