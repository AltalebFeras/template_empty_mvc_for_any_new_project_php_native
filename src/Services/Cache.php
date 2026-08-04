<?php

namespace App\Services;

/**
 * Multi-Tier Cache Service.
 *
 * L1: In-memory array (per-request, zero latency).
 * L2: Redis/Predis (cross-request, if available).
 * L3: File-based (cross-request fallback).
 *
 * Usage:
 *   Cache::set('user:42', $userData, 3600);
 *   $user = Cache::get('user:42');
 *   $result = Cache::remember('expensive:query', 600, fn() => $db->heavyQuery());
 */
final class Cache
{
    /** @var array<string, mixed> L1 in-memory store */
    private static array $memory = [];

    private static ?object $redis = null;
    private static bool $redisChecked = false;

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key Cache key.
     * @return mixed|null The cached value, or null if not found/expired.
     */
    public static function get(string $key): mixed
    {
        // L1: Memory.
        if (array_key_exists($key, self::$memory)) {
            return self::$memory[$key];
        }

        // L2: Redis.
        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $value = $redis->get("cache:{$key}");
                if ($value !== null) {
                    $decoded = json_decode($value, true);
                    self::$memory[$key] = $decoded; // Promote to L1.
                    return $decoded;
                }
            } catch (\Throwable) {}
        }

        // L3: File.
        $data = self::fileGet($key);
        if ($data !== null) {
            self::$memory[$key] = $data; // Promote to L1.
            return $data;
        }

        return null;
    }

    /**
     * Stores a value in the cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value The value to store (must be JSON-serializable).
     * @param int    $ttl   Time-to-live in seconds (default 3600).
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): void
    {
        // L1: Always store in memory.
        self::$memory[$key] = $value;

        $encoded = json_encode($value);

        // L2: Redis.
        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $redis->setex("cache:{$key}", $ttl, $encoded);
                return; // Redis is authoritative, skip file.
            } catch (\Throwable) {}
        }

        // L3: File fallback.
        self::fileSet($key, $encoded, $ttl);
    }

    /**
     * Deletes a cached value.
     */
    public static function delete(string $key): void
    {
        unset(self::$memory[$key]);

        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                $redis->del("cache:{$key}");
            } catch (\Throwable) {}
        }

        self::fileDelete($key);
    }

    /**
     * Gets a cached value or computes and stores it.
     *
     * @param string   $key     Cache key.
     * @param int      $ttl     TTL in seconds.
     * @param callable $compute Closure that returns the value to cache.
     * @return mixed The cached or computed value.
     */
    public static function remember(string $key, int $ttl, callable $compute): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $compute();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Clears all cached data.
     */
    public static function flush(): void
    {
        self::$memory = [];

        $redis = self::getRedis();
        if ($redis !== null) {
            try {
                // Only flush cache-prefixed keys, not the entire Redis.
                $keys = $redis->keys('cache:*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Throwable) {}
        }

        // Clear file cache directory.
        $dir = self::cacheDir();
        if (is_dir($dir)) {
            $files = glob($dir . '/*.cache');
            if ($files) {
                array_map('unlink', $files);
            }
        }
    }

    // -----------------------------------------------------------------------
    // File Backend
    // -----------------------------------------------------------------------

    private static function fileGet(string $key): mixed
    {
        $path = self::cacheDir() . '/' . md5($key) . '.cache';
        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['expires_at'], $data['value'])) {
            return null;
        }

        if (time() > $data['expires_at']) {
            @unlink($path);
            return null;
        }

        return $data['value'];
    }

    private static function fileSet(string $key, string $encodedValue, int $ttl): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = json_encode([
            'expires_at' => time() + $ttl,
            'value'      => json_decode($encodedValue, true),
        ]);

        file_put_contents($dir . '/' . md5($key) . '.cache', $data, LOCK_EX);
    }

    private static function fileDelete(string $key): void
    {
        $path = self::cacheDir() . '/' . md5($key) . '.cache';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private static function cacheDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache';
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
