<?php

/**
 * Router — attribute-based route dispatcher.
 *
 * Auto-discovers all #[Route] attributes on controller methods,
 * matches the current request path and method, then dispatches.
 *
 * Middleware pipeline (executed in order):
 *   1. CSRF validation (non-safe methods)
 *   2. Authentication guard (if authRequired)
 *   3. RBAC role check (if roles specified)
 *   4. ABAC permission check (if permissions specified)
 *   5. Rate limiting (if route is rate-limited)
 *   6. Controller method invocation
 */

use App\Controllers\HomeController;
use App\Services\Authorization;
use App\Services\ConfigRouter;
use App\Services\Csrf;
use App\Services\Route;

// Normalize the path: remove leading/trailing slashes, then re-add the leading one.
$route  = '/' . trim($_SERVER['REDIRECT_URL'] ?? '/', '/');
$method = ConfigRouter::getMethod();

// -----------------------------------------------------------------------
// CSRF validation for all state-changing requests.
// -----------------------------------------------------------------------
$safeMethods = ['GET', 'HEAD', 'OPTIONS'];
if (!in_array($method, $safeMethods, true)) {
    // Support per-form CSRF tokens.
    $token  = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $formId = $_POST['_csrf_form_id'] ?? null;

    if (!Csrf::validateToken($token, $formId)) {
        http_response_code(403);
        try {
            \App\Services\Logger::channel('security')->warning('CSRF validation failed', [
                'ip'     => ConfigRouter::getClientIp(),
                'path'   => $route,
                'method' => $method,
            ]);
        } catch (\Throwable) {
            // Logger not available — fail silently.
        }
        (new HomeController())->page403();
    }
}

// -----------------------------------------------------------------------
// Route Discovery & Dispatch
// -----------------------------------------------------------------------
$controllerDir = __DIR__ . '/../Controllers';
$dispatched    = false;

foreach (glob($controllerDir . '/*.php') as $file) {
    $className = 'App\\Controllers\\' . basename($file, '.php');

    if (!class_exists($className)) {
        continue;
    }

    $reflectionClass = new ReflectionClass($className);

    foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
        foreach ($reflectionMethod->getAttributes(Route::class) as $attribute) {
            /** @var Route $routeAttr */
            $routeAttr = $attribute->newInstance();

            // Match path and HTTP method.
            if ($routeAttr->path !== $route || !in_array($method, $routeAttr->methods, true)) {
                continue;
            }

            // --- Authentication Guard ---
            if ($routeAttr->authRequired) {
                if (!isset($_SESSION['connected']) || !ConfigRouter::checkOriginConnection()) {
                    ConfigRouter::redirect((\App\Services\Config::baseUrl()) . '/login');
                }
            }

            // --- RBAC Role Check ---
            if (!empty($routeAttr->roles)) {
                if (!Authorization::hasAnyRole($routeAttr->roles)) {
                    http_response_code(403);
                    try {
                        \App\Services\Logger::channel('security')->warning('RBAC denied', [
                            'ip'            => ConfigRouter::getClientIp(),
                            'path'          => $route,
                            'required_roles' => $routeAttr->roles,
                            'user_role'     => Authorization::currentRole(),
                        ]);
                    } catch (\Throwable) {}
                    (new HomeController())->page403();
                }
            }

            // --- ABAC Permission Check ---
            if (!empty($routeAttr->permissions)) {
                foreach ($routeAttr->permissions as $perm) {
                    if (!Authorization::can($perm)) {
                        http_response_code(403);
                        try {
                            \App\Services\Logger::channel('security')->warning('Permission denied', [
                                'ip'         => ConfigRouter::getClientIp(),
                                'path'       => $route,
                                'permission' => $perm,
                                'user_role'  => Authorization::currentRole(),
                            ]);
                        } catch (\Throwable) {}
                        (new HomeController())->page403();
                    }
                }
            }

            // --- Dispatch ---
            $reflectionMethod->invoke(new $className());
            $dispatched = true;
            break 3;
        }
    }
}

if (!$dispatched) {
    (new HomeController())->page404();
}
