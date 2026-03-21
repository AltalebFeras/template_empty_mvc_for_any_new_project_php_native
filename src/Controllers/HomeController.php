<?php

namespace src\Controllers;

use src\Abstracts\AbstractController;
use src\Services\Route;

class HomeController extends AbstractController
{
    #[Route('/')]
    public function displayHomepage(): void
    {
        $this->render('home/home');
    }

    #[Route('/403')]
    public function page403(): void
    {
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html; charset=utf-8");
        $this->render('home/403');
    }

    #[Route('/404')]
    public function page404(): void
    {
        header("HTTP/1.1 404 Not Found");
        header("Content-Type: text/html; charset=utf-8");
        $this->render('home/404');
    }

    #[Route('/500')]
    public function page500(): void
    {
        http_response_code(500);
        header("Content-Type: text/html; charset=utf-8");
        $this->render('home/500');
    }
}
