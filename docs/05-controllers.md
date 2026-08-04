# Controllers

Controllers handle HTTP requests and produce responses. All controllers extend `AbstractController`, which provides view rendering and redirect capabilities.

**Source files:** `src/Abstracts/AbstractController.php`, `src/Controllers/*.php`

---

## AbstractController

The base class all controllers inherit from.

### `render(string $view, array $data = []): void`

Renders a PHP view template and outputs it to the browser.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$view` | `string` | View path relative to `src/Views/`, without `.php` extension |
| `$data` | `array` | Associative array of variables available inside the view |

```php
$this->render('home/home');
$this->render('products/list', ['products' => $products, 'total' => $count]);
```

**Security:** Data is extracted inside an isolated closure scope, so a key like `$data['view']` cannot overwrite the internal path variable.

### `redirect(string $route, array $query = [], mixed $errors = null): never`

Redirects the user to another route.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$route` | `string` | Route path (e.g., `'login'`, `'dashboard'`) |
| `$query` | `array` | Query parameters appended to the URL |
| `$errors` | `mixed` | If non-empty, stored in `$_SESSION['errors']` and `?error=true` is added |

```php
// Simple redirect
$this->redirect('dashboard');

// Redirect with errors
$this->redirect('login', [], ['Invalid email or password.']);

// Redirect with query params
$this->redirect('products', ['page' => 2, 'sort' => 'name']);
```

---

## Built-In Controllers

### HomeController

Handles public pages and error pages.

| Route | Method | Description |
|-------|--------|-------------|
| `GET /` | `displayHomepage()` | Renders the public homepage |
| `GET /403` | `page403()` | 403 Forbidden error page |
| `GET /404` | `page404()` | 404 Not Found error page |
| `GET /500` | `page500()` | 500 Server Error page |

### UserController

Full authentication flow with security stack.

| Route | Method | Guards | Description |
|-------|--------|--------|-------------|
| `GET /login` | `displayLoginForm()` | — | Show login form |
| `POST /login` | `processLogin()` | Rate limit, Turnstile | Authenticate user |
| `POST /logout` | `logout()` | — | Destroy session |
| `GET /dashboard` | `displayDashboard()` | `authRequired` | Protected page |

**Login process in detail:**

1. **Rate limiting** — `RateLimiter::enforce('login', 5, 60)` — max 5 attempts per minute
2. **Turnstile** — `Turnstile::verify()` — bot detection
3. **Validation** — `Validator::validate()` — email format, password length
4. **Authentication** — `UserRepository::findByEmail()` + `PasswordHasher::verify()`
5. **Rehash** — `PasswordHasher::needsRehash()` — transparent Argon2id upgrade
6. **Session hardening:**
   - `session_regenerate_id(true)` — prevent session fixation
   - `Csrf::refreshToken()` — prevent CSRF token reuse
   - Store user data + IP + User-Agent fingerprint

### HealthController

Container orchestration probes.

| Route | Method | Description |
|-------|--------|-------------|
| `GET /health` | `health()` | Returns database + Redis health status (JSON) |
| `GET /ready` | `ready()` | Returns readiness status (JSON) |

### FileController

Secure file serving from storage.

| Route | Method | Guards | Description |
|-------|--------|--------|-------------|
| `GET /file?name=...` | `serve()` | `authRequired` | Serves uploaded files with MIME headers |

Security measures:
- Validates filename format (`/^[a-f0-9]{32}\.[a-z0-9]+$/`)
- `realpath()` check prevents directory traversal
- MIME type detected via `finfo` (not user-supplied)
- `X-Content-Type-Options: nosniff` header
- Images/videos served inline; documents as attachment

---

## Creating a New Controller

### Step 1: Create the File

```php
// src/Controllers/ProductController.php

namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Services\Route;

class ProductController extends AbstractController
{
    #[Route('/products', methods: ['GET'])]
    public function index(): void
    {
        $repo = new \App\Repositories\ProductRepository();
        $products = $repo->getAll();

        $this->render('products/index', ['products' => $products]);
    }

    #[Route('/products', methods: ['POST'], authRequired: true, permissions: ['products.write'])]
    public function store(): void
    {
        $errors = \App\Services\Validator::validate($_POST, [
            'name'  => ['required', 'min:2', 'max:255'],
            'price' => ['required', 'positiveInt'],
        ]);

        if (!empty($errors)) {
            $this->redirect('products/create', [], $errors);
        }

        $repo = new \App\Repositories\ProductRepository();
        $repo->create([
            'name'    => $_POST['name'],
            'price'   => (int) $_POST['price'],
            'user_id' => $_SESSION['user_id'],
        ]);

        $_SESSION['success'] = 'Product created successfully.';
        $this->redirect('products');
    }
}
```

### Step 2: Create the View

```php
<!-- src/Views/products/index.php -->
<?php include_once __DIR__ . '/../includes/header.php'; ?>
<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<main>
    <h1>Products</h1>
    <?php include_once __DIR__ . '/../includes/messages.php'; ?>

    <?php foreach ($products as $product): ?>
        <div class="card">
            <h2><?= htmlspecialchars($product->getName(), ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Price: <?= (int) $product->getPrice() ?> €</p>
        </div>
    <?php endforeach; ?>
</main>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
```

### Step 3: Create the Repository (if needed)

```php
// src/Repositories/ProductRepository.php

namespace App\Repositories;

use App\Abstracts\AbstractRepository;

class ProductRepository extends AbstractRepository
{
    // Auto-maps to table `products` and entity `App\Entities\Product`
    // Inherits: getAll, getById, create, updateById, deleteById, count, etc.
}
```

### Step 4: Create the Entity (if needed)

```php
// src/Entities/Product.php

namespace App\Entities;

use App\Services\Hydration;

class Product
{
    use Hydration;

    private int $productId;
    private string $name;
    private int $price;
    private int $userId;

    // Getters and setters...
}
```

---

## Best Practices

1. **Always escape output** in views with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` or `Validator::escape($value)`.
2. **Release session lock** with `session_write_close()` on read-only pages to avoid blocking concurrent requests.
3. **Validate all input** with `Validator::validate()` before processing.
4. **Use IDOR checks** with `AbstractRepository::isOwnedBy()` before update/delete operations.
5. **Log security events** with `Logger::channel('security')`.
