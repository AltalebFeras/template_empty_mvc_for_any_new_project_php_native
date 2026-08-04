# Architecture

## Design Principles

| Principle | Implementation |
|-----------|---------------|
| **Defense-in-Depth** | Multiple overlapping security layers (CSRF, rate limiting, auth, RBAC, input validation, output encoding) |
| **Convention over Configuration** | Repositories auto-map to tables and entities by naming convention |
| **Fail-Fast** | Required `.env` keys validated at boot; strict PDO error mode |
| **PSR-4 Autoloading** | `App\` namespace maps to `src/` via Composer |
| **Singleton Pattern** | Config, Database, and Cache share one instance per request |
| **Separation of Concerns** | Controllers → Services → Repositories → Entities |

---

## Request Lifecycle

```
Client Request
    │
    ▼
┌─────────────────────────────┐
│  public/index.php           │  Entry point (document root)
│  └── require src/init.php   │
└─────────────────────────────┘
    │
    ▼
┌─────────────────────────────┐
│  1. Composer Autoloader     │  vendor/autoload.php
│  2. Config::boot()          │  Load .env, validate required keys
│  3. Error Handler Setup     │  Exception → Logger → 500 page
│  4. Session Initialization  │  Secure cookies, idle timeout,
│     │                       │  periodic regeneration
│     ▼                       │
│  5. SecurityHeaders::send() │  CSP, HSTS, X-Frame-Options, etc.
│  6. RequestLogger::log()    │  Timer starts, shutdown handler registered
└─────────────────────────────┘
    │
    ▼
┌─────────────────────────────┐
│  router.php                 │
│  ├── CSRF Validation        │  On POST/PUT/PATCH/DELETE
│  ├── Route Discovery        │  Scan #[Route] attributes on controllers
│  ├── Path + Method Match    │  Compare request to route definitions
│  ├── Auth Guard             │  Check $_SESSION['connected'] if authRequired
│  ├── RBAC Role Check        │  Verify user role if roles specified
│  ├── ABAC Permission Check  │  Verify permissions if specified
│  └── Dispatch               │  Call controller method
└─────────────────────────────┘
    │
    ▼
┌─────────────────────────────┐
│  Controller Method          │
│  ├── Input Validation       │  Validator::validate()
│  ├── Business Logic         │  Service calls, repository queries
│  ├── render() or            │  HTML view rendering
│  │   ApiResponse::success() │  JSON API response
│  └── redirect()             │  HTTP redirect
└─────────────────────────────┘
    │
    ▼
┌─────────────────────────────┐
│  Shutdown                   │
│  ├── RequestLogger logs     │  Method, path, status, duration, memory
│  └── Session written        │  $_SESSION persisted
└─────────────────────────────┘
```

---

## Directory Structure

```
project-root/
│
├── bin/                              # CLI entry points
│   ├── migrate.php                   # Database migration runner
│   └── worker.php                    # Background job queue worker
│
├── docker/                           # Docker service configs
│   └── nginx/
│       └── default.conf              # Production Nginx vhost
│
├── docs/                             # Documentation (this folder)
│   ├── README.md                     # Documentation index
│   ├── 01-getting-started.md
│   ├── ...
│   └── 21-entities-hydration.md
│
├── logs/                             # Log files (gitignored)
│   ├── app-2024-01-15.log            # Application logs
│   ├── security-2024-01-15.log       # Security event logs
│   ├── database-2024-01-15.log       # Database error logs
│   ├── mail-2024-01-15.log           # Email delivery logs
│   ├── security-threats.log          # Fail2Ban-compatible threat log
│   └── php-errors.log                # PHP error log (production)
│
├── public/                           # Web-accessible document root
│   ├── index.php                     # Single entry point
│   ├── .htaccess                     # Apache rewrite + security rules
│   ├── robots.txt                    # Search engine directives
│   ├── sitemap.xml                   # SEO sitemap
│   └── assets/
│       ├── css/app.css               # Stylesheets
│       ├── js/                       # JavaScript files
│       └── imgs/                     # Static images
│
├── src/                              # Application code (App\ namespace)
│   ├── Abstracts/
│   │   ├── AbstractController.php    # Base controller (render, redirect)
│   │   └── AbstractRepository.php    # Base repository (CRUD, transactions)
│   │
│   ├── Controllers/
│   │   ├── FileController.php        # Secure file serving
│   │   ├── HealthController.php      # /health and /ready endpoints
│   │   ├── HomeController.php        # Public pages (home, 403, 404, 500)
│   │   └── UserController.php        # Authentication (login, logout)
│   │
│   ├── Entities/
│   │   └── User.php                  # User data model
│   │
│   ├── Middleware/
│   │   ├── Cors.php                  # Cross-Origin Resource Sharing
│   │   ├── RateLimiter.php           # Sliding-window rate limiting
│   │   ├── RequestLogger.php         # Request timing and logging
│   │   ├── SecurityHeaders.php       # HTTP security headers
│   │   └── ThreatLogger.php          # Fail2Ban security logging
│   │
│   ├── Migrations/
│   │   ├── 2024_001_create_users_table.php
│   │   ├── 2024_002_create_roles_permissions_tables.php
│   │   └── 2024_003_create_jobs_table.php
│   │
│   ├── Repositories/
│   │   └── UserRepository.php        # User data access
│   │
│   ├── Services/
│   │   ├── ApiResponse.php           # Normalized JSON API responses
│   │   ├── Authorization.php         # RBAC/ABAC authorization engine
│   │   ├── Cache.php                 # Multi-tier caching
│   │   ├── CircuitBreaker.php        # External API resilience
│   │   ├── Config.php                # Environment configuration loader
│   │   ├── ConfigRouter.php          # Request helper utilities
│   │   ├── Csrf.php                  # CSRF token management
│   │   ├── Database.php              # PDO singleton connection
│   │   ├── Encryption.php            # AES-256-GCM encryption
│   │   ├── FileUpload.php            # Secure file upload handler
│   │   ├── Hydration.php             # Entity auto-hydration trait
│   │   ├── ImageProcessor.php        # Image sanitization & thumbnails
│   │   ├── JobQueue.php              # Background job queue
│   │   ├── Logger.php                # PSR-3 structured logging
│   │   ├── Mail.php                  # SMTP email service
│   │   ├── Migrator.php              # Database migration engine
│   │   ├── PasswordHasher.php        # Argon2id password hashing
│   │   ├── ResponseCompressor.php    # gzip + ETag compression
│   │   ├── Route.php                 # Route attribute definition
│   │   ├── router.php               # Route dispatcher (procedural)
│   │   ├── Turnstile.php             # Cloudflare Turnstile verification
│   │   └── Validator.php             # Input validation engine
│   │
│   ├── Views/
│   │   ├── dashboard/
│   │   │   └── dashboard.php         # Protected dashboard page
│   │   ├── home/
│   │   │   ├── 403.php               # Forbidden error page
│   │   │   ├── 404.php               # Not found error page
│   │   │   ├── 500.php               # Server error page
│   │   │   └── home.php              # Public homepage
│   │   └── includes/
│   │       ├── footer.php            # HTML footer partial
│   │       ├── header.php            # HTML head + opening body
│   │       ├── messages.php          # Flash message renderer
│   │       ├── navbar.php            # Navigation bar partial
│   │       └── turnstile.php         # Cloudflare Turnstile widget
│   │
│   └── init.php                      # Application bootstrap
│
├── storage/                          # Uploaded files & cache (gitignored)
│   ├── cache/                        # File-based cache storage
│   │   ├── rate_limits/              # Rate limiter data
│   │   └── circuit_breaker/          # Circuit breaker state
│   └── uploads/                      # User-uploaded files
│
├── tests/                            # PHPUnit test suite
│   ├── bootstrap.php                 # Test environment setup
│   ├── Unit/
│   │   ├── CsrfTest.php
│   │   ├── EncryptionTest.php
│   │   ├── PasswordHasherTest.php
│   │   └── ValidatorTest.php
│   └── Integration/                  # (empty — add integration tests here)
│
├── .env                              # Environment config (NEVER commit)
├── .env.example                      # Environment template
├── .gitignore                        # Git ignore rules
├── composer.json                     # Composer config & autoloading
├── docker-compose.yml                # Docker services definition
├── Dockerfile                        # Multi-stage Docker build
├── opcache.ini                       # OPcache production config
├── phpstan.neon                      # PHPStan static analysis config
├── phpunit.xml                       # PHPUnit test config
├── readme.md                         # Project overview
└── SECURITY.md                       # Security operations runbook
```

---

## Layered Architecture

```
┌──────────────────────────────────────────────────┐
│                  Middleware Layer                 │
│  SecurityHeaders │ RateLimiter │ Cors │ CSRF     │
├──────────────────────────────────────────────────┤
│                  Routing Layer                   │
│  Route attributes │ Auth guard │ RBAC │ ABAC     │
├──────────────────────────────────────────────────┤
│                Controller Layer                  │
│  HomeController │ UserController │ HealthCtrl    │
├──────────────────────────────────────────────────┤
│                 Service Layer                    │
│  Validator │ Encryption │ Cache │ Mail │ Logger  │
├──────────────────────────────────────────────────┤
│               Repository Layer                   │
│  AbstractRepository │ UserRepository             │
├──────────────────────────────────────────────────┤
│                 Entity Layer                     │
│  User (+ Hydration trait)                        │
├──────────────────────────────────────────────────┤
│               Infrastructure                     │
│  Database (PDO) │ Config (.env) │ Redis │ Files  │
└──────────────────────────────────────────────────┘
```

---

## Design Patterns Used

| Pattern | Where | Purpose |
|---------|-------|---------|
| **Singleton** | `Config`, `Database`, `Cache` | One instance per request — avoids duplicate connections |
| **Repository** | `AbstractRepository`, `UserRepository` | Isolates database logic from controllers |
| **Template Method** | `AbstractController::render()` | Base class defines rendering, subclasses customize |
| **Strategy** | `RateLimiter` (Redis vs File), `Cache` (L1/L2/L3) | Interchangeable storage backends |
| **Circuit Breaker** | `CircuitBreaker` | Prevents cascading failures from external services |
| **Observer** | `register_shutdown_function` in `RequestLogger` | Log timing after request completes |
| **Builder** | `ApiResponse` | Fluent API for constructing JSON responses |
| **Attribute/Annotation** | `#[Route]` | Declarative routing metadata on controller methods |
| **Hydration** | `Hydration` trait | Automatic property population from database rows |
