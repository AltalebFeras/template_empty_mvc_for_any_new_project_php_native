<?php

namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Services\Route;

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
        http_response_code(403);
        header("Content-Type: text/html; charset=utf-8");
        $this->render('home/403');
    }

    #[Route('/404')]
    public function page404(): void
    {
        http_response_code(404);
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
