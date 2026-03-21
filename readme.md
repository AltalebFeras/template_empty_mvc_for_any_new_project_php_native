# Template Empty MVC for Any New Project (PHP Native)

> **MVC PHP Framework**  
> Created by [Feras Altaleb](https://github.com/AltalebFeras) — for educational purposes only.

---

A lightweight, extensible MVC framework built with native PHP to help you kickstart web applications quickly and efficiently.

> **Requires PHP 8.0+** (uses native PHP Attributes for routing).

---

## 🚀 Features

- **MVC Architecture** — Clean separation of concerns using the Model-View-Controller pattern.
- **Attribute-Based Routing** — Symfony-style `#[Route]` attributes declared directly on controller methods. No central routes file needed.
- **Database Abstraction** — Easy database interactions via PDO with a built-in `AbstractRepository`.
- **Security** — Session hijacking protection, method spoofing, HTTPS detection, and real IP resolution.
- **Extensible** — Easily add new controllers; routes are auto-discovered via Reflection.
- **Lightweight** — Minimal dependencies for optimal performance.

---

## 🛠️ Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/AltalebFeras/template_empty_mvc_for_any_new_project_php_native.git
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Your Environment

- Copy `config_example.php` and rename it to `config.php`.
- Update the settings as needed for your environment (development or production):
  - Set up your **database connection** in `config.php`.
  - Set your **base URL** (`HOME_URL`) in `config.php`.
  - Configure **mail settings** in `config.php`.
  - Set your **timezone** in `src/init.php`.

### 4. Define Your Routes

Routes are defined with the `#[Route]` attribute directly above each controller method — no central routes file.

```php
use src\Services\Route;

class UserController
{
    // Public page — GET only
    #[Route('/login', methods: ['GET'])]
    public function showLoginForm(): void
    {
        // render login view
    }

    // Form submission — POST only
    #[Route('/login', methods: ['POST'])]
    public function handleLogin(): void
    {
        // process credentials
    }

    // Protected page — requires active session
    #[Route('/dashboard', methods: ['GET'], authRequired: true)]
    public function showDashboard(): void
    {
        // render dashboard view
    }
}
```

**`#[Route]` parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$path` | `string` | *(required)* | URL path to match (e.g. `'/login'`) |
| `$methods` | `string\|array` | `['GET']` | Allowed HTTP methods |
| `$name` | `string` | `''` | Optional route name |
| `$authRequired` | `bool` | `false` | Redirect to `/login` if not authenticated |

> The router auto-discovers every class inside `src/Controllers/` — just create a new controller and add `#[Route]` attributes.

### 5. HTML Form Method Spoofing

HTML forms only support `GET` and `POST`. To send `PUT`, `PATCH`, or `DELETE`, add a hidden field:

```html
<form method="POST" action="/resource/1">
    <input type="hidden" name="_method" value="DELETE">
    ...
</form>
```

`ConfigRouter::getMethod()` will resolve the effective method automatically.

### 6. Run the Application

Access your application in the browser:

```
http://localhost/path-to-your-project/public
```

---

## 📁 Directory Structure

```
public/                   # Web root — point your server / virtual host here
│   index.php             # Single entry point
│   .htaccess             # URL rewriting (Apache)
└── assets/               # CSS, JS, images

src/
├── init.php              # Bootstrap: session, autoloader, config, router
├── Abstracts/
│   ├── AbstractController.php   # render() and redirect() helpers
│   └── AbstractRepository.php  # CRUD helpers (getAll, getById, create, …)
├── Controllers/          # Your controllers — add #[Route] attributes here
├── Entities/             # Plain PHP entity classes (hydrated via Hydration trait)
├── Migrations/           # SQL migration files
├── Repositories/         # Repository classes extending AbstractRepository
├── Services/
│   ├── Route.php         # #[Route] PHP attribute definition
│   ├── router.php        # Auto-discovers and dispatches routes via Reflection
│   ├── ConfigRouter.php  # HTTP utilities: getMethod, redirect, isAjax, getClientIp…
│   ├── Database.php      # PDO connection wrapper
│   ├── Encrypt_decrypt.php
│   ├── Hydration.php     # Trait for automatic entity hydration
│   ├── Mail.php          # PHPMailer wrapper
│   └── Validator.php
└── Views/                # PHP view templates
```

---

## 🔒 Security Utilities (`ConfigRouter`)

| Method | Description |
|---|---|
| `ConfigRouter::getMethod()` | Returns the real HTTP method, supporting `PUT`/`PATCH`/`DELETE` spoofing via `_method` POST field |
| `ConfigRouter::checkOriginConnection()` | Validates session IP & user-agent to detect session hijacking — returns `false` on mismatch |
| `ConfigRouter::redirect($url, $code)` | Safe redirect with HTTP status code (default 302) |
| `ConfigRouter::isAjax()` | Detects `XMLHttpRequest` / `fetch` requests |
| `ConfigRouter::isHttps()` | Returns `true` if the connection is HTTPS |
| `ConfigRouter::getClientIp()` | Resolves the real client IP (proxy-aware, validated) |

---

## 🧰 Best Practices

- Use `$authRequired: true` on routes that require a logged-in user.
- Never expose the `src/` directory — only `public/` should be the web root.
- Store passwords with `password_hash()` / `password_verify()`.
- Validate and sanitize all user input at the controller level.
- Test your application thoroughly before deploying to production.

---

## 🤝 Contributing

Contributions are welcome!  
If you have suggestions for improvements or new features, please [open an issue](https://github.com/AltalebFeras/template_empty_mvc_for_any_new_project_php_native/issues) or submit a pull request.

---

## 👤 Author

**Feras Altaleb**  
[GitHub](https://github.com/AltalebFeras)

---

## ⭐️ Enjoy building amazing web applications with this simple MVC framework!
