<?php

namespace src\Abstracts;

use Error;
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
            throw new \Exception("View not found: {$view}");
        }
    }
    /**
     * Redirect to a specified route with optional query parameters.
     **query error=true with this format array ['error' => 'true'] should be added to the URL for all error happen.
     * @param string $route The route exists and defined in the router to redirect to.
     * @param array $query An associative array of query parameters to append to the URL , it is empty by default.
     * @return void
     */
    public function redirect($route, array $query = []): void
    {
        $url = HOME_URL . $route;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        header("Location: {$url}");
        // $this->unsetFormData();
        exit();
    }

    /**
     * Handle and redirect all errors to a specified route.
     * This method checks if the $errors parameter is not empty, and if so, it stores the errors in the session and redirects to the specified route with an error query parameter.
     * If the $errors parameter is empty, it unsets the 'errors' session variable.
     * @param mixed $errors
     * @param mixed $route
     * @return void
     */
    public function returnAllErrors($errors, $route)
    {
        if (!empty($errors)) {
            $_SESSION['errors'] = $errors;
            $this->redirect($route, ['error' => 'true']);
        } else {
            unset($_SESSION['errors']);
        }
    }
}
