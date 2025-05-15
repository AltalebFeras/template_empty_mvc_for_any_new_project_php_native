<?php

namespace src\Controllers;

class HomeController
{
    public function displayHomepage(): void
    {
        include_once __DIR__ . '/../Views/home/home.php';
    }

    public function page404(): void
    {
        header("HTTP/1.1 404 Not Found");
        header("Content-Type: text/html; charset=utf-8");
        include_once __DIR__ . '/../Views/home/404.php';
        exit();
    }
    public function page403(): void
    {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html; charset=utf-8");
        include_once __DIR__ . '/../Views/home/403.php';
        exit();
    }
}
