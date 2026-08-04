<?php

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Thread-safe Singleton Database Connection.
 *
 * Maintains a single PDO instance per request lifecycle, preventing
 * the overhead of creating new connections per repository.
 *
 * Usage:
 *   $pdo = Database::getInstance();
 *   $stmt = $pdo->prepare('SELECT ...');
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * Returns the singleton PDO instance. Creates it on first call.
     *
     * @throws RuntimeException If the database connection fails.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }

        return self::$instance;
    }

    /**
     * Establishes the PDO connection with hardened settings.
     */
    private static function connect(): PDO
    {
        try {
            $driver  = Config::get('DB_DRIVER', 'mysql');
            $host    = Config::get('DB_HOST', '127.0.0.1');
            $port    = Config::getInt('DB_PORT', 3306);
            $dbname  = Config::get('DB_NAME', '');
            $charset = Config::get('DB_CHARSET', 'utf8mb4');

            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
            }

            $pdo = new PDO($dsn, Config::get('DB_USER', ''), Config::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            // MySQL-specific: ensure proper row count on UPDATE.
            if ($driver === 'mysql') {
                $pdo->setAttribute(PDO::MYSQL_ATTR_FOUND_ROWS, true);
            }

            return $pdo;
        } catch (PDOException $e) {
            // Log the real error, throw a generic message.
            try {
                Logger::channel('database')->critical('Database connection failed', [
                    'message' => $e->getMessage(),
                    'host'    => Config::get('DB_HOST'),
                    'dbname'  => Config::get('DB_NAME'),
                ]);
            } catch (\Throwable) {
                error_log('DB CRITICAL: ' . $e->getMessage());
            }

            throw new RuntimeException('Database connection failed.');
        }
    }

    /**
     * Checks if the database connection is alive.
     * Used by health check endpoints.
     */
    public static function isHealthy(): bool
    {
        try {
            $pdo = self::getInstance();
            $pdo->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Resets the singleton (useful for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    // Prevent instantiation, cloning, and unserialization.
    private function __construct() {}
    private function __clone() {}
}
