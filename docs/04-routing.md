# Routing

The framework uses PHP 8 attributes for declarative, annotation-style routing. Routes are defined directly on controller methods using the `#[Route]` attribute.

**Source files:** `src/Services/Route.php`, `src/Services/router.php`

---

## Defining Routes

### Basic Route

```php
use App\Services\Route;

class HomeController extends AbstractController
{
    #[Route('/')]
    public function homepage(): void
    {
        $this->render('home/home');
    }
}
```

### Route with HTTP Method

```php
#[Route('/login', methods: ['GET'])]
public function showLoginForm(): void { ... }

#[Route('/login', methods: ['POST'])]
public function processLogin(): void { ... }
```

### Multiple Methods

```php
#[Route('/api/users', methods: ['GET', 'POST'])]
public function handleUsers(): void { ... }
```

### Protected Routes (Authentication Required)

```php
#[Route('/dashboard', methods: ['GET'], authRequired: true)]
public function dashboard(): void { ... }
```

When `authRequired: true`, the router checks:
1. `$_SESSION['connected']` is set
2. `ConfigRouter::checkOriginConnection()` passes (IP + UA match)

If either check fails, the user is redirected to `/login`.

### Role-Restricted Routes (RBAC)

```php
#[Route('/admin', methods: ['GET'], authRequired: true, roles: ['admin'])]
public function adminPanel(): void { ... }

#[Route('/editor', roles: ['admin', 'editor'])]
public function editorPanel(): void { ... }
```

The `roles` parameter accepts an array. The user needs **any one** of the listed roles.

### Permission-Restricted Routes (ABAC)

```php
#[Route('/posts/create', methods: ['POST'], permissions: ['posts.write'])]
public function createPost(): void { ... }

#[Route('/users/delete', methods: ['DELETE'], permissions: ['users.delete', 'admin.access'])]
public function deleteUser(): void { ... }
```

The `permissions` parameter accepts an array. The user needs **all** listed permissions.

### Combining Guards

```php
#[Route('/admin/users', methods: ['DELETE'], authRequired: true, roles: ['admin'], permissions: ['users.delete'])]
public function deleteUser(): void { ... }
```

Guards are checked in this order:
1. Authentication (is the user logged in?)
2. Role check (does the user have the required role?)
3. Permission check (does the user have all required permissions?)

---

## Route Attribute Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$path` | `string` | *(required)* | URL path to match (e.g., `/login`, `/api/users`) |
| `$methods` | `string\|array` | `['GET']` | Allowed HTTP methods |
| `$name` | `string` | `''` | Optional route name (for future URL generation) |
| `$authRequired` | `bool` | `false` | Require authenticated session |
| `$roles` | `array` | `[]` | Required roles (any match) |
| `$permissions` | `array` | `[]` | Required permissions (all must match) |

---

## How Route Discovery Works

The `router.php` file:

1. Scans all PHP files in `src/Controllers/`.
2. Uses `ReflectionClass` and `ReflectionMethod` to find `#[Route]` attributes.
3. Instantiates each `Route` attribute and compares its `path` and `methods` against the current request.
4. On match, runs the middleware pipeline (CSRF → Auth → RBAC → ABAC).
5. Invokes the controller method.
6. If no route matches, calls `HomeController::page404()`.

---

## CSRF Protection

CSRF validation is automatic for all **state-changing** HTTP methods:

| Method | CSRF Required? |
|--------|---------------|
| GET | ❌ No |
| HEAD | ❌ No |
| OPTIONS | ❌ No |
| POST | ✅ Yes |
| PUT | ✅ Yes |
| PATCH | ✅ Yes |
| DELETE | ✅ Yes |

The token is read from:
1. `$_POST['_csrf_token']` (form submissions)
2. `$_SERVER['HTTP_X_CSRF_TOKEN']` (AJAX/API requests)

See [Security → CSRF](06-security.md#csrf-protection) for token generation and validation details.

---

## HTTP Method Spoofing

HTML forms only support `GET` and `POST`. To use `PUT`, `PATCH`, or `DELETE` from a form, add a hidden field:

```html
<form method="POST" action="/users/42">
    <?= \App\Services\Csrf::inputField() ?>
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete User</button>
</form>
```

The `ConfigRouter::getMethod()` method checks for `$_POST['_method']` and overrides the request method accordingly. Only `GET`, `POST`, `PUT`, `PATCH`, `DELETE` are accepted as spoofed values.

---

## Request Helpers

`ConfigRouter` provides utility methods for working with the current request:

| Method | Returns | Description |
|--------|---------|-------------|
| `getMethod()` | `string` | Effective HTTP method (with `_method` override support) |
| `checkOriginConnection()` | `bool` | Validates session IP + User-Agent fingerprint |
| `redirect($url, $code)` | `never` | Sends a redirect response and exits |
| `isAjax()` | `bool` | True if `X-Requested-With: XMLHttpRequest` |
| `isHttps()` | `bool` | True if the request is over HTTPS |
| `getClientIp()` | `string` | Real client IP (proxy-aware) |

---

## Adding a New Route

1. Create or open a controller in `src/Controllers/`.
2. Add a public method with a `#[Route]` attribute.
3. The route is automatically discovered — no registration needed.

```php
namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Services\Route;

class ProductController extends AbstractController
{
    #[Route('/products', methods: ['GET'])]
    public function listProducts(): void
    {
        // Fetch products...
        $this->render('products/list', ['products' => $products]);
    }

    #[Route('/products', methods: ['POST'], authRequired: true, permissions: ['products.write'])]
    public function createProduct(): void
    {
        // Validate, create, redirect...
    }
}
```

---

## Error Pages

The router dispatches to `HomeController` for error pages:

| HTTP Status | Route | Method |
|-------------|-------|--------|
| 403 Forbidden | `/403` | `page403()` |
| 404 Not Found | `/404` | `page404()` |
| 500 Internal Server Error | `/500` | `page500()` |

The `.htaccess` file also maps Apache `ErrorDocument` directives to these routes.
