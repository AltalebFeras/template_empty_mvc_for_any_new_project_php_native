<?php

use src\Controllers\HomeController;
use src\Services\ConfigRouter;
use src\Services\Route;

$route  = $_SERVER['REDIRECT_URL'] ?? '/';
$method = ConfigRouter::getMethod();

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
