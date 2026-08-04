<?php

namespace App\Middleware;

use App\Services\Config;
use App\Services\ConfigRouter;
use App\Services\Logger;

/**
 * IP/User-Based Rate Limiter — Sliding Window Algorithm.
 *
 * Limits the number of requests a client can make within a configurable
 * time window. Uses file-based storage by default, Redis if available.
 *
 * Usage in controllers:
 *   RateLimiter::check('login', 5, 60);   // 5 attempts per 60 seconds
 *   RateLimiter::check('api', 60, 60);    // 60 requests per 60 seconds
 *
 * The router can also call it automatically for annotated routes.
 */
final class RateLimiter
{
    private static ?object $redis = null;
    private static bool $redisChecked = false;

    /**
     * Checks the rate limit for the current client.
     *
     * @param string   $action  The action being rate-limited (e.g., 'login', 'api').
     * @param int      $maxAttempts Maximum allowed attempts in the window.
     * @param int      $windowSeconds Length of the sliding window in seconds.
     * @param string|null $identifier Custom identifier (defaults to client IP).
     * @return bool True if the request is allowed, false if rate-limited.
     */
    public static function check(
        string  $action,
        int     $maxAttempts = 60,
        int     $windowSeconds = 60,
        ?string $identifier = null,
    ): bool {
        $identifier = $identifier ?? ConfigRouter::getClientIp();
        $key        = "rate_limit:{$action}:{$identifier}";

        $attempts = self::getAttempts($key, $windowSeconds);

        if ($attempts >= $maxAttempts) {
            // Rate limit exceeded.
            self::sendRateLimitHeaders($maxAttempts, 0, $windowSeconds);

            try {
                Logger::channel('security')->warning('Rate limit exceeded', [
                    'action'  => $action,
                    'ip'      => $identifier,
                    'limit'   => $maxAttempts,
                    'window'  => $windowSeconds,
                ]);

                // Log in Fail2Ban-compatible format.
                ThreatLogger::log('rate_limit', $identifier, [
                    'action'   => $action,
                    'attempts' => $attempts,
                ]);
            } catch (\Throwable) {}

            return false;
        }

        self::recordAttempt($key, $windowSeconds);
        self::sendRateLimitHeaders($maxAttempts, $maxAttempts - $attempts - 1, $windowSeconds);

        return true;
    }

    /**
     * Enforces the rate limit — returns 429 and exits if exceeded.
     */
    public static function enforce(
        string  $action,
        int     $maxAttempts = 60,
        int     $windowSeconds = 60,
        ?string $identifier = null,
    ): void {
        if (!self::check($action, $maxAttempts, $windowSeconds, $identifier)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'error',
                'errors' => ['Too many requests. Please try again later.'],
                'meta'   => ['retry_after' => $windowSeconds],
            ]);
            exit;
        }
    }

    /**
     * Sends standard rate-limit HTTP headers.
     */
    private static function sendRateLimitHeaders(int $limit, int $remaining, int $reset): void
    {
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: " . max(0, $remaining));
        header("Retry-After: {$reset}");
    }

    /**
     * Gets the number of attempts in the current window.
     */
    private static function getAttempts(string $key, int $windowSeconds): int
    {
        $redis = self::getRedis();

        if ($redis !== null) {
            return self::getAttemptsRedis($redis, $key, $windowSeconds);
        }

        return self::getAttemptsFile($key, $windowSeconds);
    }

    /**
     * Records an attempt.
     */
    private static function recordAttempt(string $key, int $windowSeconds): void
    {
        $redis = self::getRedis();

        if ($redis !== null) {
            self::recordAttemptRedis($redis, $key, $windowSeconds);
        } else {
            self::recordAttemptFile($key, $windowSeconds);
        }
    }

    // -----------------------------------------------------------------------
    // Redis Backend
    // -----------------------------------------------------------------------

    private static function getAttemptsRedis(object $redis, string $key, int $window): int
    {
        $now = microtime(true);
        $redis->zremrangebyscore($key, '-inf', (string) ($now - $window));
        return (int) $redis->zcard($key);
    }

    private static function recordAttemptRedis(object $redis, string $key, int $window): void
    {
        $now = microtime(true);
        $redis->zadd($key, [$now => (string) $now]);
        $redis->expire($key, $window + 1);
    }

    // -----------------------------------------------------------------------
    // File Backend (fallback)
    // -----------------------------------------------------------------------

    private static function getAttemptsFile(string $key, int $window): int
    {
        $data = self::readFileStore($key);
        $now  = time();

        // Filter to only entries within the window.
        $data = array_filter($data, fn(int $ts) => ($now - $ts) < $window);

        return count($data);
    }

    private static function recordAttemptFile(string $key, int $window): void
    {
        $data   = self::readFileStore($key);
        $now    = time();
        $data[] = $now;

        // Prune old entries.
        $data = array_filter($data, fn(int $ts) => ($now - $ts) < $window);

        self::writeFileStore($key, array_values($data));
    }

    private static function getStorePath(string $key): string
    {
        $dir  = dirname(__DIR__, 2) . '/storage/cache/rate_limits';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . md5($key) . '.json';
    }

    private static function readFileStore(string $key): array
    {
        $path = self::getStorePath($key);
        if (!file_exists($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private static function writeFileStore(string $key, array $data): void
    {
        $path = self::getStorePath($key);
        file_put_contents($path, json_encode($data), LOCK_EX);
    }

    // -----------------------------------------------------------------------
    // Redis Connection
    // -----------------------------------------------------------------------

    private static function getRedis(): ?object
    {
        if (self::$redisChecked) {
            return self::$redis;
        }

        self::$redisChecked = true;

        if (!class_exists(\Predis\Client::class)) {
            return null;
        }

        try {
            $client = new \Predis\Client([
                'host'     => Config::get('REDIS_HOST', '127.0.0.1'),
                'port'     => Config::getInt('REDIS_PORT', 6379),
                'password' => Config::get('REDIS_PASSWORD') ?: null,
            ]);
            $client->ping();
            self::$redis = $client;
        } catch (\Throwable) {
            self::$redis = null;
        }

        return self::$redis;
    }
}

// -----------------------------------------------------------------------
// Companion: ThreatLogger (Fail2Ban-compatible)
// -----------------------------------------------------------------------
// Loaded from separate file — see ThreatLogger.php
