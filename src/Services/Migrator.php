<?php

namespace App\Services;

use PDO;

/**
 * Lightweight Database Migration System.
 *
 * Reads timestamped PHP migration files from src/Migrations/ and tracks
 * applied migrations in a `_migrations` database table.
 *
 * Migration files must return an array with 'up' and 'down' SQL strings.
 *
 * CLI: php bin/migrate.php up|down|status
 */
final class Migrator
{
    private const TABLE = '_migrations';
    private PDO $db;
    private string $migrationsDir;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->migrationsDir = dirname(__DIR__) . '/Migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Runs all pending migrations.
     */
    public function up(): array
    {
        $applied = $this->getApplied();
        $files   = $this->getMigrationFiles();
        $ran     = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $migration = require $file;

            if (!isset($migration['up'])) {
                echo "⚠ Skipping {$name} — no 'up' key found.\n";
                continue;
            }

            try {
                if (!$this->db->inTransaction()) {
                    $this->db->beginTransaction();
                }
                $this->db->exec($migration['up']);
                $this->recordMigration($name);
                if ($this->db->inTransaction()) {
                    $this->db->commit();
                }
                $ran[] = $name;
                echo "✓ Migrated: {$name}\n";
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                echo "✗ Failed: {$name} — {$e->getMessage()}\n";
                break;
            }
        }

        if (empty($ran)) {
            echo "Nothing to migrate.\n";
        }

        return $ran;
    }

    /**
     * Rolls back the last migration.
     */
    public function down(): ?string
    {
        $applied = $this->getApplied();

        if (empty($applied)) {
            echo "Nothing to roll back.\n";
            return null;
        }

        $last = end($applied);
        $file = $this->migrationsDir . '/' . $last . '.php';

        if (!file_exists($file)) {
            echo "✗ Migration file not found: {$last}\n";
            return null;
        }

        $migration = require $file;

        if (!isset($migration['down'])) {
            echo "✗ No 'down' key in {$last}\n";
            return null;
        }

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }
            $this->db->exec($migration['down']);
            $this->removeMigration($last);
            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            echo "✓ Rolled back: {$last}\n";
            return $last;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            echo "✗ Rollback failed: {$last} — {$e->getMessage()}\n";
            return null;
        }
    }

    /**
     * Displays the status of all migrations.
     */
    public function status(): void
    {
        $applied = $this->getApplied();
        $files   = $this->getMigrationFiles();

        echo str_pad('Migration', 50) . "Status\n";
        echo str_repeat('-', 60) . "\n";

        foreach ($files as $file) {
            $name   = pathinfo($file, PATHINFO_FILENAME);
            $status = in_array($name, $applied, true) ? '✓ Applied' : '○ Pending';
            echo str_pad($name, 50) . "{$status}\n";
        }
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function getApplied(): array
    {
        $stmt = $this->db->query("SELECT `migration` FROM `" . self::TABLE . "` ORDER BY `id` ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsDir . '/*.php');
        sort($files);
        return $files ?: [];
    }

    private function recordMigration(string $name): void
    {
        $stmt = $this->db->prepare("INSERT INTO `" . self::TABLE . "` (`migration`) VALUES (:name)");
        $stmt->execute([':name' => $name]);
    }

    private function removeMigration(string $name): void
    {
        $stmt = $this->db->prepare("DELETE FROM `" . self::TABLE . "` WHERE `migration` = :name");
        $stmt->execute([':name' => $name]);
    }
}
