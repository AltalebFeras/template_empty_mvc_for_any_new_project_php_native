# Entities & Hydration Trait

Covers the entity domain model pattern and automatic property hydration via the `Hydration` trait.

**Source files:** `src/Services/Hydration.php`, `src/Entities/User.php`

---

## Entity Pattern

Entities represent core domain models (e.g. `User`, `Product`, `Order`). They encapsulate object state with private/protected properties and public getter/setter methods.

```php
namespace App\Entities;

use App\Services\Hydration;

class User
{
    use Hydration;

    private int $userId;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $password;
    private bool $isActivated;
    private int $roleId;
    private mixed $createdAt;

    // Getters and Setters...
    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $id): self { $this->userId = $id; return $this; }

    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $name): self { $this->firstName = $name; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
}
```

---

## How the `Hydration` Trait Works

The `Hydration` trait provides automatic conversion from database column names (snake_case) to entity setter methods (camelCase).

### Snake_case to Setter Mapping

| Database Column | Setter Method Invoked |
|-----------------|----------------------|
| `user_id` | `setUserId($value)` |
| `first_name` | `setFirstName($value)` |
| `is_activated` | `setIsActivated($value)` |
| `role_id` | `setRoleId($value)` |

### Constructor Auto-Hydration

The trait constructor accepts an associative data array:

```php
$user = new User([
    'user_id'    => 42,
    'first_name' => 'Jane',
    'last_name'  => 'Doe',
    'email'      => 'jane@example.com',
]);

echo $user->getFirstName(); // "Jane"
```

### Magic `__set` Setter

Dynamic property assignments invoke setter hydration:

```php
$user = new User();
$user->first_name = 'Jane'; // Invokes setFirstName('Jane')
```

---

## Serialization Support

The trait implements PHP 8 `__serialize()` and `__unserialize()` methods:

- `__serialize()`: Scans object methods via Reflection for public `get*()` methods and extracts entity state into an array.
- `__unserialize($data)`: Passes serialized data back through `hydrate($data)` to reconstruct the object.

```php
$serialized   = serialize($user);
$unserialized = unserialize($serialized); // Restored User object with state intact
```

---

## PDO Automatic Hydration

When fetching rows from the database, PDO automatically hydra-instantiates entity objects using `PDO::FETCH_CLASS`:

```php
$stmt = $this->DB->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute([':id' => 42]);
$user = $stmt->fetchObject(\App\Entities\User::class);
// Calls new User($row) which triggers Hydration trait
```
