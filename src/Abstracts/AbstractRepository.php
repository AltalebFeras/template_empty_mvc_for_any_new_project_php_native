<?php

namespace App\Abstracts;

use Generator;
use PDO;
use App\Services\Database;

/**
 * Abstract Repository — base class for all entity repositories.
 *
 * Provides:
 * - CRUD operations with prepared statements
 * - Transaction helpers (beginTransaction, commit, rollBack, transaction)
 * - Generator-based streaming for large result sets
 * - Ownership verification for IDOR prevention
 * - Column key validation against SQL injection
 *
 * Convention: A repository named `UserRepository` auto-maps to
 * table `users` and entity `App\Entities\User`.
 */
abstract class AbstractRepository
{
    protected PDO $DB;
    private string $class;
    private string $table;

    public function __construct()
    {
        $this->DB = Database::getInstance();

        // Derive entity class and table name from the repository class name.
        $model       = get_class($this);
        $this->class = str_replace(['App\\Repositories\\', 'Repository'], '', $model);
        $this->table = strtolower($this->class) . 's';
    }

    // -----------------------------------------------------------------------
    // READ operations
    // -----------------------------------------------------------------------

    /**
     * Retrieves all records from the table.
     *
     * @return array<object> Array of entity objects.
     */
    public function getAll(): array
    {
        $sql  = "SELECT * FROM `{$this->table}`";
        $stmt = $this->DB->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_CLASS, "App\\Entities\\{$this->class}");
    }

    /**
     * Streams all records using a Generator for memory-efficient iteration.
     *
     * Use this instead of getAll() when processing large result sets.
     * Each row is yielded one at a time — memory usage stays constant.
     *
     * Usage:
     *   foreach ($repo->getAllStream() as $entity) { ... }
     *
     * @return Generator<object>
     */
    public function getAllStream(): Generator
    {
        $sql  = "SELECT * FROM `{$this->table}`";
        $stmt = $this->DB->prepare($sql);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $entityClass = "App\\Entities\\{$this->class}";
            yield new $entityClass($row);
        }
    }

    /**
     * Retrieves a record by its primary key.
     *
     * @param int $id The record ID.
     * @return object|null The entity object, or null if not found.
     */
    public function getById(int $id): object|null
    {
        $pk   = strtolower($this->class) . '_id';
        $sql  = "SELECT * FROM `{$this->table}` WHERE `{$pk}` = :id LIMIT 1";
        $stmt = $this->DB->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchObject("App\\Entities\\{$this->class}");
        return $result ?: null;
    }

    /**
     * Returns the total number of records in the table.
     */
    public function count(): int
    {
        $sql  = "SELECT COUNT(*) FROM `{$this->table}`";
        $stmt = $this->DB->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // -----------------------------------------------------------------------
    // WRITE operations
    // -----------------------------------------------------------------------

    /**
     * Creates a new record.
     *
     * @param array<string, mixed> $data Column => value pairs.
     * @return bool True on success.
     */
    public function create(array $data): bool
    {
        $data         = $this->prepareData($data);
        $columns      = implode(', ', array_map(fn($k) => '`' . $this->validateColumnKey($k) . '`', array_keys($data)));
        $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));

        $sql  = "INSERT INTO `{$this->table}` ($columns) VALUES ($placeholders)";
        $stmt = $this->DB->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    }

    /**
     * Updates a record by its primary key.
     *
     * @param int                  $id   The record ID.
     * @param array<string, mixed> $data Column => value pairs to update.
     * @return bool True on success.
     */
    public function updateById(int $id, array $data): bool
    {
        $data = $this->prepareData($data);
        $pk   = strtolower($this->class) . '_id';

        $setClause = implode(', ', array_map(
            fn($k) => '`' . $this->validateColumnKey($k) . '` = :' . $k,
            array_keys($data)
        ));

        $sql  = "UPDATE `{$this->table}` SET $setClause WHERE `{$pk}` = :id";
        $stmt = $this->DB->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Deletes a record by its primary key.
     *
     * @param int $id The record ID.
     * @return bool True on success.
     */
    public function deleteById(int $id): bool
    {
        $pk   = strtolower($this->class) . '_id';
        $sql  = "DELETE FROM `{$this->table}` WHERE `{$pk}` = :id";
        $stmt = $this->DB->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Returns the auto-increment ID generated by the last INSERT.
     */
    public function getLastInsertId(): int
    {
        return (int) $this->DB->lastInsertId();
    }

    // -----------------------------------------------------------------------
    // IDOR Prevention — Ownership Verification
    // -----------------------------------------------------------------------

    /**
     * Verifies that a record belongs to a specific user.
     *
     * Call this before any update/delete operation on user-owned resources
     * to prevent Insecure Direct Object Reference (IDOR) attacks.
     *
     * @param int    $id          The record ID.
     * @param int    $userId      The authenticated user's ID.
     * @param string $ownerColumn The column containing the owner's user ID (default: 'user_id').
     * @return bool True if the record belongs to the user.
     */
    public function isOwnedBy(int $id, int $userId, string $ownerColumn = 'user_id'): bool
    {
        $pk    = strtolower($this->class) . '_id';
        $owner = $this->validateColumnKey($ownerColumn);

        $sql  = "SELECT COUNT(*) FROM `{$this->table}` WHERE `{$pk}` = :id AND `{$owner}` = :user_id LIMIT 1";
        $stmt = $this->DB->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    // -----------------------------------------------------------------------
    // Transaction Helpers
    // -----------------------------------------------------------------------

    /**
     * Begins a database transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->DB->beginTransaction();
    }

    /**
     * Commits the current transaction.
     */
    public function commit(): bool
    {
        return $this->DB->commit();
    }

    /**
     * Rolls back the current transaction.
     */
    public function rollBack(): bool
    {
        return $this->DB->rollBack();
    }

    /**
     * Wraps a callback in a transaction.
     *
     * Automatically commits on success or rolls back on exception.
     *
     * Usage:
     *   $repo->transaction(function() use ($repo, $data) {
     *       $repo->create($data);
     *       $repo->updateById($id, ['status' => 'active']);
     *   });
     *
     * @param callable $callback The operations to execute inside the transaction.
     * @return mixed The return value of the callback.
     * @throws \Throwable Re-throws any exception after rolling back.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Internal Helpers
    // -----------------------------------------------------------------------

    /**
     * Prepares data values for SQL binding (e.g., DateTime → string).
     */
    private function prepareData(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
                $data[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        return $data;
    }

    /**
     * Validates that a column key contains only safe identifier characters.
     *
     * @throws \InvalidArgumentException If the key is not a valid SQL identifier.
     */
    private function validateColumnKey(string $key): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException("Invalid column name: '{$key}'");
        }
        return $key;
    }
}
