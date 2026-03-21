<?php

namespace src\Abstracts;

use Exception;

abstract class AbstractController
{
    /**
     * Render a view file with the given data.
     *
     * @param string $view The name of the view file and if it is in a folder should add the folder name like folder name/file name (without extension).
     * @param array $data An associative array of data to pass to the view, it is empty by default.
     * @return void
     */
    public function render($view, array $data = []): void
    {
        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (file_exists($viewPath)) {
            extract($data);
            include $viewPath;
            // $this->unsetFormData();
        } else {
            throw new Exception("View not found: {$view}");
        }
    }
    /**
     * Redirect to a specified route with optional query parameters and error handling.
     * If $errors is provided and not empty, stores them in the session and appends error=true to the URL.
     * If $errors is empty, unsets the session errors and redirects normally.
     * @param string $route The route exists and defined in the router to redirect to.
     * @param array $query An associative array of query parameters to append to the URL, empty by default.
     * @param mixed $errors Optional errors to store in the session; triggers error=true query param when not empty.
     * @return void
     */
    public function redirect(string $route, array $query = [], mixed $errors = null): void
    {
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $query['error'] = 'true';
        } else {
            unset($_SESSION['errors']);
        }

        $url = HOME_URL . $route;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        http_response_code(302);
        header("Location: {$url}");
        exit();
    }
}
