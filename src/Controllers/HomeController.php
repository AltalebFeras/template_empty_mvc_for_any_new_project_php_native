<?php

namespace src\Controllers;

use src\Services\Route;

class HomeController
{
    #[Route('/')]
    public function displayHomepage(): void
    {
        include_once __DIR__ . '/../Views/home/home.php';
    }

    #[Route('/403')]
    public function page403(): void
    {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html; charset=utf-8");
        include_once __DIR__ . '/../Views/home/403.php';
        exit();
    }

    #[Route('/404')]
    public function page404(): void
    {
        header("HTTP/1.1 404 Not Found");
        header("Content-Type: text/html; charset=utf-8");
        include_once __DIR__ . '/../Views/home/404.php';
        exit();
    }

    #[Route('/500')]
    public function page500(): void
    {
        http_response_code(500);
        header("Content-Type: text/html; charset=utf-8");
        include_once __DIR__ . '/../Views/home/500.php';
        exit();
    }
}
