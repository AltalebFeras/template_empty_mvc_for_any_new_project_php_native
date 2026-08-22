<?php

namespace App\Abstracts;

use Exception;

abstract class AbstractController
{
    /**
     * Render a view file with the given data.
     *
     * Data is extracted inside an isolated closure scope so it cannot
     * overwrite internal variables (e.g. a $view key in $data won't
     * clobber the path resolution above).
     *
     * @param string $view The view path relative to Views/, without .php extension
     *                     (e.g. 'home/home' or 'dashboard/dashboard').
     * @param array  $data Associative array of variables to expose inside the view.
     */
    public function render(string $view, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (!file_exists($viewPath)) {
            throw new Exception("View not found: {$view}");
        }
        (function (string $__path, array $__data): void {
            extract($__data);
            include $__path;
        })($viewPath, $data);
        exit();
    }

    /**
     * Redirect to a specified route with optional query parameters and error handling.
     * If $errors is provided and not empty, stores them in the session and appends error=true to the URL.
     * If $errors is empty, unsets the session errors and redirects normally.
     * @param string $route The route exists and defined in the router to redirect to.
     * @param array $query An associative array of query parameters to append to the URL, empty by default.
     * @param mixed $errors Optional errors to store in the session; triggers error=true query param when not empty.
     * @return never
     */
    public function redirect(string $route, array $query = [], mixed $errors = null): never
    {
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $query['error'] = 'true';
        } else {
            unset($_SESSION['errors']);
        }

        $baseUrl = \App\Services\Config::baseUrl();
        $url     = $baseUrl . '/' . ltrim($route, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        http_response_code(302);
        header("Location: {$url}");
        exit();
    }
}
