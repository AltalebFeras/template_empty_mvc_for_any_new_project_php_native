<?php

use src\Controllers\HomeController;
use src\Services\ConfigRouter;
use src\Services\Csrf;
use src\Services\Route;

// Normalize the path: remove leading/trailing slashes, then re-add the leading one.
// This ensures '/login', '/login/', and '//login' all resolve to '/login'.
$route  = '/' . trim($_SERVER['REDIRECT_URL'] ?? '/', '/');
$method = ConfigRouter::getMethod();

// -----------------------------------------------------------------------
// CSRF validation for all state-changing requests.
// Reads from the hidden form field OR the X-CSRF-Token header (for AJAX).
// -----------------------------------------------------------------------
$safeMethods = ['GET', 'HEAD', 'OPTIONS'];
if (!in_array($method, $safeMethods, true)) {
    $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!Csrf::validateToken($token)) {
        (new HomeController())->page403();
    }
}

// Auto-discover all routes defined via #[Route] attributes in every controller.
$controllerDir = __DIR__ . '/../Controllers';
$dispatched    = false;

foreach (glob($controllerDir . '/*.php') as $file) {
    $className = 'src\\Controllers\\' . basename($file, '.php');

    // Trigger PSR-4 autoloading for this controller class.
    if (!class_exists($className)) {
        continue;
    }

    $reflectionClass = new ReflectionClass($className);

    foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
        foreach ($reflectionMethod->getAttributes(Route::class) as $attribute) {
            /** @var Route $routeAttr */
            $routeAttr = $attribute->newInstance();

            // Match path and HTTP method.
            if ($routeAttr->path !== $route || !in_array($method, $routeAttr->methods)) {
                continue;
            }

            // Optional authentication guard.
            if ($routeAttr->authRequired) {
                if (!isset($_SESSION['connected']) || !ConfigRouter::checkOriginConnection()) {
                    ConfigRouter::redirect(HOME_URL . 'login');
                }
            }

            $reflectionMethod->invoke(new $className());
            $dispatched = true;
            break 3;
        }
    }
}

if (!$dispatched) {
    (new HomeController())->page404();
}

// Auto-discover all routes defined via #[Route] attributes in every controller.
$controllerDir = __DIR__ . '/../Controllers';
$dispatched    = false;

foreach (glob($controllerDir . '/*.php') as $file) {
    $className = 'src\\Controllers\\' . basename($file, '.php');

    // Trigger PSR-4 autoloading for this controller class.
    if (!class_exists($className)) {
        continue;
    }

    $reflectionClass = new ReflectionClass($className);

    foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
        foreach ($reflectionMethod->getAttributes(Route::class) as $attribute) {
            /** @var Route $routeAttr */
            $routeAttr = $attribute->newInstance();

            // Match path and HTTP method.
            if ($routeAttr->path !== $route || !in_array($method, $routeAttr->methods)) {
                continue;
            }

            // Optional authentication guard.
            if ($routeAttr->authRequired) {
                if (!isset($_SESSION['connected']) || !ConfigRouter::checkOriginConnection()) {
                    ConfigRouter::redirect(HOME_URL . 'login');
                }
            }

            $reflectionMethod->invoke(new $className());
            $dispatched = true;
            break 3;
        }
    }
}

if (!$dispatched) {
    (new HomeController())->page404();
}
