<?php

namespace App\Middleware;

use App\Services\Logger;

/**
 * Request Logger Middleware.
 *
 * Logs every incoming HTTP request: method, path, status code,
 * duration, and memory peak usage. Excludes static asset requests.
 *
 * Call early in init.php so the timer starts before dispatch.
 */
final class RequestLogger
{
    private static float $startTime = 0;

    /**
     * Logs the current request. Call early in the lifecycle.
     */
    public static function log(): void
    {
        self::$startTime = microtime(true);

        // Skip logging for static assets.
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        if (preg_match('/\.(css|js|jpg|jpeg|png|svg|webp|gif|ico|woff2?|ttf|map)$/i', $path)) {
            return;
        }

        // Register shutdown function to capture the response status.
        register_shutdown_function([self::class, 'onShutdown']);
    }

    /**
     * Called on script shutdown to log the completed request.
     */
    public static function onShutdown(): void
    {
        $duration    = round((microtime(true) - self::$startTime) * 1000, 2);
        $memoryPeak  = round(memory_get_peak_usage(true) / 1048576, 2);
        $statusCode  = http_response_code() ?: 200;
        $method      = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $path        = $_SERVER['REQUEST_URI'] ?? '/';

        try {
            Logger::channel('app')->info('Request completed', [
                'method'      => $method,
                'path'        => $path,
                'status'      => $statusCode,
                'duration_ms' => $duration,
                'memory_mb'   => $memoryPeak,
            ]);
        } catch (\Throwable) {
            // Logger is unavailable — silently ignore.
        }
    }
}
