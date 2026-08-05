<?php

namespace App\Services;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Singleton configuration manager.
 *
 * Loads environment variables from `.env` via vlucas/phpdotenv and exposes
 * them through typed getters. Validates that all required keys are present
 * on first load to fail fast during deployment.
 *
 * Usage:
 *   $dbHost = Config::get('DB_HOST');
 *   $debug  = Config::getBool('APP_DEBUG');
 *   $port   = Config::getInt('DB_PORT', 3306);
 */
final class Config
{
    private static ?self $instance = null;

    /** @var array<string, string> Loaded environment values */
    private array $values = [];

    /** Keys that MUST be present in .env */
    private const REQUIRED_KEYS = [
        'APP_ENV',
        'APP_KEY',
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
    ];

    private function __construct()
    {
        $rootPath = dirname(__DIR__, 2);

        if (!file_exists($rootPath . '/.env')) {
            throw new RuntimeException(
                'Missing .env file. Copy .env.example to .env and configure it.'
            );
        }

        $dotenv = Dotenv::createImmutable($rootPath);
        $dotenv->load();
        $dotenv->required(self::REQUIRED_KEYS)->notEmpty();

        // Snapshot all loaded values for fast lookup.
        foreach ($_ENV as $key => $value) {
            $this->values[$key] = $value;
        }
    }

    /**
     * Boots the configuration (idempotent).
     */
    public static function boot(): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    /**
     * Returns a configuration value as a string.
     *
     * @param string      $key     Environment variable name.
     * @param string|null $default Fallback if the key is not set.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        self::boot();
        return self::$instance->values[$key] ?? $_ENV[$key] ?? $default;
    }

    /**
     * Returns a configuration value as a boolean.
     *
     * Treats 'true', '1', 'yes', 'on' (case-insensitive) as true.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Returns a configuration value as an integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value !== null ? (int) $value : $default;
    }

    /**
     * Returns true if the application is running in production.
     */
    public static function isProduction(): bool
    {
        return self::get('APP_ENV', 'development') === 'production';
    }

    /**
     * Returns true if debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return self::getBool('APP_DEBUG', false);
    }

    /**
     * Returns the binary encryption key (32 bytes decoded from hex).
     *
     * @throws RuntimeException If APP_KEY is missing or wrong length.
     */
    public static function getEncryptionKey(): string
    {
        $hex = self::get('APP_KEY', '');
        if ($hex === '' || strlen($hex) !== 64) {
            throw new RuntimeException(
                'APP_KEY must be exactly 64 hex characters (32 bytes). '
                . 'Generate with: php -r "echo bin2hex(random_bytes(32));"'
            );
        }
        $key = hex2bin($hex);
        if ($key === false) {
            throw new RuntimeException('APP_KEY contains invalid hex characters.');
        }
        return $key;
    }

    /**
     * Returns the application base URL with no trailing slash.
     */
    public static function baseUrl(): string
    {
        return rtrim(self::get('APP_URL', 'http://localhost'), '/');
    }

    /**
     * Prevents cloning of the singleton.
     */
    private function __clone() {}
}
