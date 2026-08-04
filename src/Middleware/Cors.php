<?php

namespace App\Middleware;

use App\Services\Config;

/**
 * CORS Middleware — Fine-Grained Cross-Origin Resource Sharing.
 *
 * Handles preflight OPTIONS requests and sets CORS headers on all responses.
 * Configuration is driven by .env or hardcoded defaults.
 *
 * Usage in init.php or router.php:
 *   Cors::handle();
 */
final class Cors
{
    /**
     * Processes CORS headers and handles preflight requests.
     *
     * @param array<string> $allowedOrigins  Allowed origins (default: same-origin only).
     * @param array<string> $allowedMethods  Allowed HTTP methods.
     * @param array<string> $allowedHeaders  Allowed request headers.
     * @param bool          $allowCredentials Whether to allow credentials (cookies).
     * @param int           $maxAge          Preflight cache duration in seconds.
     */
    public static function handle(
        array $allowedOrigins = [],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With'],
        bool  $allowCredentials = true,
        int   $maxAge = 86400,
    ): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Default to same-origin only if no origins configured.
        if (empty($allowedOrigins)) {
            $appUrl = Config::baseUrl();
            $allowedOrigins = [$appUrl];
        }

        // Check if the requesting origin is allowed.
        if (in_array($origin, $allowedOrigins, true) || in_array('*', $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
        } else {
            // Origin not allowed — don't set CORS headers.
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(403);
                exit;
            }
            return;
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        header('Access-Control-Max-Age: ' . $maxAge);

        if ($allowCredentials) {
            header('Access-Control-Allow-Credentials: true');
        }

        header('Vary: Origin');

        // Handle preflight OPTIONS request.
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
