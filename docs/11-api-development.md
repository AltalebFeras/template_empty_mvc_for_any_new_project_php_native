# API Development

This document covers building JSON APIs: response formatting, CORS, and rate limiting.

**Source files:** `src/Services/ApiResponse.php`, `src/Middleware/Cors.php`, `src/Middleware/RateLimiter.php`

---

## ApiResponse

All API responses use a consistent JSON structure:

```json
{
    "status": "success",
    "data": { ... },
    "errors": null,
    "meta": null
}
```

### Success Responses

```php
use App\Services\ApiResponse;

// 200 OK
ApiResponse::success(['user' => $userData]);

// 200 with metadata
ApiResponse::success($items, ['total' => 150]);

// 201 Created
ApiResponse::created(['id' => $newId, 'name' => 'New Item']);

// 204 No Content
ApiResponse::noContent();
```

### Error Responses

```php
// 400 Bad Request (default)
ApiResponse::error(['Invalid input.']);

// 422 Unprocessable Entity (validation errors)
ApiResponse::error(['email' => 'Email is required.', 'name' => 'Name is too short.'], 422);

// 404 Not Found
ApiResponse::error(['Resource not found.'], 404);

// 500 Internal Server Error
ApiResponse::error(['An unexpected error occurred.'], 500);
```

### Paginated Responses

```php
$items   = $repo->getPage($page, $perPage);
$total   = $repo->count();

ApiResponse::paginated($items, $total, $page, $perPage);
```

Output:
```json
{
    "status": "success",
    "data": [ ... ],
    "errors": null,
    "meta": {
        "pagination": {
            "total": 150,
            "per_page": 20,
            "current_page": 3,
            "last_page": 8
        }
    }
}
```

### Response Headers

All `ApiResponse` methods automatically set:
- `Content-Type: application/json; charset=utf-8`
- `Cache-Control: no-store, no-cache, must-revalidate`

---

## CORS Middleware

### Basic Usage

Call in `init.php` or at the start of your API controller:

```php
use App\Middleware\Cors;

Cors::handle();
```

### Configuration

```php
Cors::handle(
    allowedOrigins: ['https://frontend.example.com', 'https://admin.example.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization', 'X-CSRF-Token'],
    allowCredentials: true,
    maxAge: 86400,     // Cache preflight for 24 hours
);
```

### Default Behavior

| Parameter | Default | Description |
|-----------|---------|-------------|
| `allowedOrigins` | `[Config::baseUrl()]` | Same-origin only |
| `allowedMethods` | `GET, POST, PUT, PATCH, DELETE, OPTIONS` | All standard methods |
| `allowedHeaders` | `Content-Type, Authorization, X-CSRF-Token, X-Requested-With` | Common headers |
| `allowCredentials` | `true` | Allow cookies/auth headers |
| `maxAge` | `86400` | 24-hour preflight cache |

### Preflight Handling

When a browser sends an `OPTIONS` request (preflight), CORS middleware:
1. Checks if the `Origin` header matches an allowed origin.
2. Sets all CORS headers.
3. Returns `204 No Content` and exits.

If the origin is not allowed, returns `403 Forbidden`.

---

## Rate Limiting

### Checking Rate Limits

```php
use App\Middleware\RateLimiter;

// Returns true if allowed, false if rate-limited
$allowed = RateLimiter::check('api', 60, 60);  // 60 requests per 60 seconds

if (!$allowed) {
    ApiResponse::error(['Too many requests.'], 429);
}
```

### Enforcing Rate Limits

```php
// Automatically returns 429 and exits if exceeded
RateLimiter::enforce('login', 5, 60);  // 5 attempts per 60 seconds
```

### Response Headers

Rate limit headers are sent automatically:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
Retry-After: 60
```

### Storage Backends

| Backend | Priority | When Used |
|---------|----------|-----------|
| Redis (Predis) | Primary | When Redis is available and connected |
| File-based | Fallback | When Redis is unavailable |

The algorithm used is **sliding window** via Redis sorted sets or timestamped file entries.

### Configuration via .env

```env
RATE_LIMIT_LOGIN=5       # Max login attempts
RATE_LIMIT_API=60        # Max API requests
RATE_LIMIT_WINDOW=60     # Window size in seconds
```

### Per-User Rate Limiting

By default, rate limiting is keyed by client IP. To rate-limit per-user:

```php
RateLimiter::enforce('api', 100, 60, identifier: 'user:' . $_SESSION['user_id']);
```

---

## Building an API Controller

### Example: Products API

```php
namespace App\Controllers;

use App\Abstracts\AbstractController;
use App\Middleware\Cors;
use App\Middleware\RateLimiter;
use App\Services\ApiResponse;
use App\Services\Authorization;
use App\Services\Route;
use App\Services\Validator;

class ProductApiController extends AbstractController
{
    #[Route('/api/products', methods: ['GET'])]
    public function index(): void
    {
        Cors::handle();
        RateLimiter::enforce('api', 60, 60);

        $repo = new \App\Repositories\ProductRepository();
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

        // Use streaming for large datasets
        $items = $repo->getAll(); // or custom paginated query
        $total = $repo->count();

        ApiResponse::paginated($items, $total, $page, $perPage);
    }

    #[Route('/api/products', methods: ['POST'], authRequired: true, permissions: ['products.write'])]
    public function store(): void
    {
        Cors::handle();

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $errors = Validator::validate($input, [
            'name'  => ['required', 'min:2', 'max:255'],
            'price' => ['required', 'positiveInt'],
        ]);

        if (!empty($errors)) {
            ApiResponse::error($errors, 422);
        }

        $repo = new \App\Repositories\ProductRepository();
        $repo->create([
            'name'    => $input['name'],
            'price'   => (int) $input['price'],
            'user_id' => $_SESSION['user_id'],
        ]);

        ApiResponse::created(['id' => $repo->getLastInsertId()]);
    }

    #[Route('/api/products', methods: ['DELETE'], authRequired: true)]
    public function destroy(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $repo = new \App\Repositories\ProductRepository();

        if (!$repo->isOwnedBy($id, $_SESSION['user_id']) && !Authorization::hasRole('admin')) {
            ApiResponse::error(['Forbidden'], 403);
        }

        $repo->deleteById($id);
        ApiResponse::noContent();
    }
}
```

### AJAX CSRF Integration

```javascript
// Read CSRF token from a meta tag or hidden input
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_csrf_token"]')?.value;

fetch('/api/products', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ name: 'New Product', price: 1999 }),
});
```
