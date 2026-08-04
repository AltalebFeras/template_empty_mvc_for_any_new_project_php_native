<?php

namespace App\Services;

/**
 * Response Compressor — HTTP Caching & Output Compression.
 *
 * Handles:
 *  - Content negotiation for gzip/brotli encoding
 *  - ETag generation and 304 Not Modified responses
 *  - Cache-Control headers for dynamic and static responses
 *
 * Usage (call early in the request lifecycle):
 *   ResponseCompressor::start();
 *
 * At the end of the response (or via register_shutdown_function):
 *   ResponseCompressor::finish();
 */
final class ResponseCompressor
{
    private static bool $started = false;

    /**
     * Starts output buffering with compression if the client supports it.
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        self::$started = true;

        // Check if the client accepts compressed responses.
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        if (str_contains($acceptEncoding, 'gzip') && function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }

    /**
     * Finishes output buffering, sends ETag and caching headers.
     *
     * Call this at the end of the response or let it run via shutdown handler.
     *
     * @param int    $maxAge      Cache-Control max-age in seconds (0 = no-cache).
     * @param bool   $isPublic    Whether the response can be cached by CDNs/proxies.
     * @param string $cachePolicy Custom Cache-Control directive override.
     */
    public static function finish(int $maxAge = 0, bool $isPublic = false, string $cachePolicy = ''): void
    {
        if (!self::$started) {
            return;
        }

        $content = ob_get_contents();

        if ($content === false) {
            return;
        }

        // --- ETag Header ---
        $etag = '"' . md5($content) . '"';
        header('ETag: ' . $etag);

        // Check If-None-Match for 304 response.
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($ifNoneMatch === $etag) {
            // Content hasn't changed — send 304 and skip the body.
            ob_end_clean();
            http_response_code(304);
            self::$started = false;
            return;
        }

        // --- Cache-Control ---
        if ($cachePolicy !== '') {
            header('Cache-Control: ' . $cachePolicy);
        } elseif ($maxAge > 0) {
            $visibility = $isPublic ? 'public' : 'private';
            header("Cache-Control: {$visibility}, max-age={$maxAge}");
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        // Vary header to signal that encoding affects the response.
        header('Vary: Accept-Encoding');

        ob_end_flush();
        self::$started = false;
    }

    /**
     * Sets static asset cache headers (for controller-served files).
     *
     * @param int    $maxAge  Cache duration in seconds.
     * @param string $etag    Optional custom ETag value.
     */
    public static function staticHeaders(int $maxAge = 86400, string $etag = ''): void
    {
        header("Cache-Control: public, max-age={$maxAge}, immutable");

        if ($etag !== '') {
            header('ETag: "' . $etag . '"');

            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            if ($ifNoneMatch === '"' . $etag . '"') {
                http_response_code(304);
                exit;
            }
        }

        header('Vary: Accept-Encoding');
    }
}
