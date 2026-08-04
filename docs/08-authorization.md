# Authorization (RBAC / ABAC)

The framework implements both **Role-Based Access Control (RBAC)** and **Attribute-Based Access Control (ABAC)** for fine-grained resource protection.

**Source file:** `src/Services/Authorization.php`

---

## Role Hierarchy

Roles are hierarchical — higher roles inherit the access level of lower roles:

```
admin (3)  ──▶  editor (2)  ──▶  user (1)  ──▶  guest (0)
```

| Role | Level | Description |
|------|-------|-------------|
| `guest` | 0 | Unauthenticated visitor |
| `user` | 1 | Standard authenticated user |
| `editor` | 2 | Content editor with extended privileges |
| `admin` | 3 | Full system administrator |

### Checking Roles

```php
// Does the user have 'editor' role or higher?
Authorization::hasRole('editor'); // true for editor AND admin

// Does the user have any of these roles?
Authorization::hasAnyRole(['admin', 'editor']); // true if role is admin or editor
```

---

## Permission System

Each role has a set of permissions. The admin role has `'*'` (wildcard), granting all permissions.

### Default Permission Map

| Role | Permissions |
|------|-------------|
| `guest` | `pages.read` |
| `user` | `pages.read`, `profile.read`, `profile.write`, `files.upload` |
| `editor` | Everything in `user` + `posts.read`, `posts.write`, `posts.delete`, `users.read` |
| `admin` | `*` (all permissions) |

### Checking Permissions

```php
// Exact permission check
Authorization::can('posts.write'); // true for editor, admin

// Wildcard — admin has '*', so:
Authorization::can('anything.you.want'); // true for admin
```

### Category Wildcards

You can define category-level permissions:

```php
// If a role has 'users.*', it matches:
Authorization::can('users.read');   // true
Authorization::can('users.write');  // true
Authorization::can('users.delete'); // true
```

---

## Route-Level Authorization

Apply roles and permissions directly on route attributes:

```php
// Require authentication
#[Route('/profile', authRequired: true)]

// Require specific role
#[Route('/admin', authRequired: true, roles: ['admin'])]

// Require specific permission
#[Route('/posts/create', methods: ['POST'], permissions: ['posts.write'])]

// Require role AND permission
#[Route('/users/ban', methods: ['POST'], roles: ['admin'], permissions: ['users.write'])]
```

---

## Code-Level Authorization (Guards)

### `requireRole(string $role): void`

Throws `RuntimeException` (→ 403) if the user doesn't have the required role.

```php
Authorization::requireRole('admin');
// Continues execution if admin, throws 403 otherwise
```

### `requirePermission(string $permission): void`

Throws `RuntimeException` (→ 403) if the user lacks the permission.

```php
Authorization::requirePermission('users.delete');
```

---

## ABAC: Resource Ownership

Prevent **Insecure Direct Object Reference (IDOR)** attacks by verifying resource ownership.

### `Authorization::owns(int $resourceOwnerId): bool`

Checks if the current user is the owner of a resource.

```php
$post = $postRepo->getById($postId);
if (!Authorization::owns($post->getUserId())) {
    // User doesn't own this post
}
```

### `Authorization::canAccess(int $resourceOwnerId): bool`

Checks if the user owns the resource **or** is an admin.

```php
if (!Authorization::canAccess($post->getUserId())) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
```

### `AbstractRepository::isOwnedBy(int $id, int $userId): bool`

Database-level ownership check via query:

```php
$repo = new PostRepository();
if (!$repo->isOwnedBy($postId, $_SESSION['user_id'])) {
    // IDOR attempt — reject
}
```

---

## API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `currentRole()` | `string` | Current user's role from session |
| `currentUserId()` | `?int` | Current user's ID from session |
| `hasRole(string $role)` | `bool` | User has this role or higher |
| `hasAnyRole(array $roles)` | `bool` | User has any of these roles |
| `can(string $permission)` | `bool` | User has this permission |
| `owns(int $ownerId)` | `bool` | Current user owns this resource |
| `canAccess(int $ownerId)` | `bool` | User owns resource OR is admin |
| `requireRole(string $role)` | `void` | Guard — throws 403 if unauthorized |
| `requirePermission(string $perm)` | `void` | Guard — throws 403 if unauthorized |

---

## Customizing Roles and Permissions

To add new roles or permissions, edit the constants in `Authorization.php`:

```php
private const ROLE_HIERARCHY = [
    'guest'     => 0,
    'user'      => 1,
    'moderator' => 2,  // NEW
    'editor'    => 3,
    'admin'     => 4,
];

private const ROLE_PERMISSIONS = [
    'moderator' => [
        'pages.read',
        'posts.read',
        'posts.moderate',  // NEW
        'comments.delete', // NEW
    ],
    // ...
];
```

For database-driven permissions (more flexible), query the `role_permissions` pivot table instead of using the hardcoded constants.
