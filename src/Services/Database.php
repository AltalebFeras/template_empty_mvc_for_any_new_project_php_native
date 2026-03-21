<?php

namespace src\Services;

use PDO;
use PDOException;

/**
 * Class Database
 *
 * Manages the database connection using PDO.
 * Loads configuration from a PHP file and handles connection errors.
 */
final class Database
{
    private ?PDO $DB = null;

    /**
     * Database constructor.
     *
     * Establishes the PDO connection. Throws a RuntimeException on failure
     * so the error propagates properly instead of being silently swallowed.
     */

    public function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $this->DB = new PDO($dsn, DB_USER, DB_PWD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $error) {
            throw new \RuntimeException('Database connection failed: ' . $error->getMessage());
        }
    }

    /**
     * Returns the PDO instance.
     *
     * @throws \RuntimeException If the database connection has not been properly established.
     * @return PDO The active PDO instance.
     */

    public function getDB(): PDO
    {
        if ($this->DB instanceof PDO) {
            return $this->DB;
        } else {
            throw new \RuntimeException("Database connection failed.");
        }
    }
}
