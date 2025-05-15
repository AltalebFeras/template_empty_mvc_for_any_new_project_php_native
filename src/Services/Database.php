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
    private $DB;
    private $config;

    /**
     * Database constructor.
     *
     * Loads the configuration file and attempts to establish a database connection using PDO.
     * Catches and displays any connection errors.
     */

    public function __construct()
    {
        $this->config = __DIR__ . '/../../config.php';
        require_once $this->config;

        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
            $this->DB = new PDO($dsn, DB_USER, DB_PWD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $error) {
            echo "Error while connecting to Database: " . $error->getMessage();
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
