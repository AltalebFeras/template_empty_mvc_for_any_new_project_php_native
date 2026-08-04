<?php

namespace App\Services;

/**
 * ConfigRouter — request helpers for routing and session verification.
 */
class ConfigRouter
{
    /**
     * Returns the effective HTTP method for the current request.
     *
     * HTML forms only support GET/POST. To use DELETE, PUT, PATCH from a form,
     * add a hidden field: <input type="hidden" name="_method" value="DELETE">
     * The real request must be POST, and _method overrides it.
     *
     * @return string Uppercase HTTP method (e.g. 'GET', 'POST', 'DELETE').
     */
    public static function getMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && !empty($_POST['_method'])) {
            $spoofed = strtoupper($_POST['_method']);
            $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
            if (in_array($spoofed, $allowed, true)) {
                $method = $spoofed;
            }
        }

        return $method;
    }

    /**
     * Checks that the current session belongs to the same browser/IP that created it.
     *
     * Because PHP cannot distinguish which client owns a stolen session ID, both
     * sessions are destroyed on mismatch. Returns false instead of redirecting so
     * the caller decides how to react.
     *
     * @return bool True if the session origin is valid, false otherwise.
     */
    public static function checkOriginConnection(): bool
    {
        if (
            !isset($_SESSION['ip_address'], $_SESSION['user_agent']) ||
            $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '') ||
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')
        ) {
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['error'] = 'Your session has expired. Please log in again.';
            return false;
        }

        return true;
    }

    /**
     * Redirects to the given URL and stops execution.
     *
     * @param string $url  Absolute or relative URL.
     * @param int    $code HTTP status code (default 302).
     */
    public static function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit();
    }

    /**
     * Returns true if the request was made via XMLHttpRequest / fetch.
     */
    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Returns true if the request is served over HTTPS.
     */
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /**
     * Returns the real client IP, taking common reverse-proxy headers into account.
     *
     * ⚠️  X-Forwarded-For can be spoofed — only trust it behind a known reverse proxy.
     */
    public static function getClientIp(): string
    {
        $candidates = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
