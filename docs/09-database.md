# Database

The database layer consists of a PDO singleton connection, an abstract repository with CRUD operations, and a migration system.

**Source files:** `src/Services/Database.php`, `src/Abstracts/AbstractRepository.php`, `src/Services/Migrator.php`

---

## Database Connection

### Singleton Pattern

`Database::getInstance()` returns a single `PDO` instance reused across all repositories within a request. This prevents creating multiple connections.

```php
use App\Services\Database;

$pdo = Database::getInstance();
$stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = :id');
$stmt->execute([':id' => 42]);
```

### PDO Configuration

| Setting | Value | Purpose |
|---------|-------|---------|
| `ERRMODE_EXCEPTION` | Enabled | Throws exceptions on SQL errors |
| `FETCH_ASSOC` | Default | Returns associative arrays by default |
| `EMULATE_PREPARES` | Disabled | Uses real prepared statements (prevents SQLi) |
| `STRINGIFY_FETCHES` | Disabled | Returns native PHP types (int, float) |
| `MYSQL_ATTR_FOUND_ROWS` | Enabled | `UPDATE` returns matched rows, not changed rows |

### Driver Support

| Driver | DSN Format | Config |
|--------|-----------|--------|
| MySQL | `mysql:host=...;port=...;dbname=...;charset=utf8mb4` | `DB_DRIVER=mysql` |
| PostgreSQL | `pgsql:host=...;port=...;dbname=...` | `DB_DRIVER=pgsql` |

### Health Check

```php
Database::isHealthy(); // true if SELECT 1 succeeds
Database::reset();     // Reset singleton (for testing)
```

---

## AbstractRepository

All repositories extend `AbstractRepository`, which auto-maps the repository to a database table and entity class by naming convention.

### Naming Convention

| Repository Class | Table Name | Entity Class |
|-----------------|------------|--------------|
| `UserRepository` | `users` | `App\Entities\User` |
| `ProductRepository` | `products` | `App\Entities\Product` |
| `OrderItemRepository` | `orderitems` | `App\Entities\OrderItem` |

### CRUD Methods

#### `getAll(): array`

Returns all records as entity objects.

```php
$users = $repo->getAll(); // array of User objects
```

#### `getAllStream(): Generator`

Returns a generator for memory-efficient iteration of large datasets. Each row is yielded one at a time.

```php
foreach ($repo->getAllStream() as $user) {
    // Memory usage stays constant regardless of table size
    processUser($user);
}
```

#### `getById(int $id): ?object`

Returns a single entity by primary key, or `null` if not found.

```php
$user = $repo->getById(42);
```

The primary key column name is derived as `{entity_name}_id` (e.g., `user_id`).

#### `count(): int`

Returns the total number of records.

```php
$total = $repo->count(); // 150
```

#### `create(array $data): bool`

Inserts a new record. Column keys are validated against SQL injection.

```php
$repo->create([
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'email'      => 'john@example.com',
    'password'   => PasswordHasher::hash('secret'),
]);
```

#### `updateById(int $id, array $data): bool`

Updates a record by primary key.

```php
$repo->updateById(42, [
    'email'    => 'new@example.com',
    'role_id'  => 2,
]);
```

#### `deleteById(int $id): bool`

Deletes a record by primary key.

```php
$repo->deleteById(42);
```

#### `getLastInsertId(): int`

Returns the auto-increment ID from the last `create()` call.

---

### Transaction Helpers

#### `transaction(callable $callback): mixed`

Wraps operations in a transaction. Automatically commits on success, rolls back on exception.

```php
$repo->transaction(function() use ($repo, $orderRepo) {
    $repo->create(['name' => 'New Product', 'stock' => 100]);
    $productId = $repo->getLastInsertId();

    $orderRepo->create(['product_id' => $productId, 'quantity' => 5]);
});
```

#### Manual Transaction Control

```php
$repo->beginTransaction();
try {
    $repo->create($data1);
    $repo->create($data2);
    $repo->commit();
} catch (\Throwable $e) {
    $repo->rollBack();
    throw $e;
}
```

---

### IDOR Prevention

#### `isOwnedBy(int $id, int $userId, string $ownerColumn = 'user_id'): bool`

Verifies that a record belongs to a specific user before allowing modification.

```php
if (!$repo->isOwnedBy($postId, $_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

$repo->deleteById($postId); // Safe — ownership verified
```

---

### Column Key Validation

All column names passed to `create()` and `updateById()` are validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`. This prevents SQL injection through column names, even though the values are always parameterized.

---

### DateTime Handling

`DateTime` and `DateTimeImmutable` objects are automatically converted to `'Y-m-d H:i:s'` format:

```php
$repo->create([
    'name'       => 'Event',
    'starts_at'  => new \DateTime('2024-06-15 10:00:00'),
]);
```

---

## Migrations

### Migration File Format

Migration files live in `src/Migrations/` and are named with timestamp prefixes for ordering:

```php
// src/Migrations/2024_004_create_posts_table.php

return [
    'up' => "
        CREATE TABLE `posts` (
            `post_id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id`    INT UNSIGNED NOT NULL,
            `title`      VARCHAR(255) NOT NULL,
            `body`       TEXT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_posts_user` (`user_id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'down' => "DROP TABLE IF EXISTS `posts`;",
];
```

### CLI Commands

```bash
php bin/migrate.php up       # Run all pending migrations
php bin/migrate.php down     # Roll back the last migration
php bin/migrate.php status   # Show migration status
```

### How Migrations Work

1. A `_migrations` table tracks applied migrations.
2. `up` runs pending migrations in filename order within a transaction.
3. `down` rolls back the last applied migration within a transaction.
4. If a migration fails, the transaction is rolled back and execution stops.

### Included Migrations

| File | Tables Created |
|------|---------------|
| `2024_001_create_users_table.php` | `users` |
| `2024_002_create_roles_permissions_tables.php` | `roles`, `permissions`, `role_permissions` + FK on `users` |
| `2024_003_create_jobs_table.php` | `jobs` |

---

## Creating a Custom Repository

```php
namespace App\Repositories;

use App\Abstracts\AbstractRepository;

class PostRepository extends AbstractRepository
{
    // Inherits: getAll, getById, create, updateById, deleteById,
    //           count, getLastInsertId, isOwnedBy, transaction, etc.

    /**
     * Custom query: find posts by user ID.
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->DB->prepare(
            "SELECT * FROM `posts` WHERE `user_id` = :uid ORDER BY `created_at` DESC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll(\PDO::FETCH_CLASS, \App\Entities\Post::class);
    }

    /**
     * Custom query: search posts by title.
     */
    public function search(string $query): array
    {
        $stmt = $this->DB->prepare(
            "SELECT * FROM `posts` WHERE `title` LIKE :query ORDER BY `created_at` DESC"
        );
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll(\PDO::FETCH_CLASS, \App\Entities\Post::class);
    }
}
```

**Important:** Always use prepared statements with parameter binding. Never concatenate user input into SQL queries.
