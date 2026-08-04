# Enterprise PHP MVC Framework Template

A hyper-secure, high-performance native PHP MVC application template designed for enterprise-grade production deployments. Zero framework dependencies — only essential, audited libraries.

## Table of Contents

- [Architecture](#architecture)
- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Migrations](#database-migrations)
- [Request Lifecycle](#request-lifecycle)
- [Security](#security)
- [Authentication](#authentication)
- [Authorization (RBAC/ABAC)](#authorization-rbacabac)
- [API Development](#api-development)
- [File Uploads](#file-uploads)
- [Caching](#caching)
- [Background Jobs](#background-jobs)
- [Cloudflare Turnstile](#cloudflare-turnstile)
- [Docker Deployment](#docker-deployment)
- [Web Server Configuration](#web-server-configuration)
- [Testing](#testing)
- [Maintenance](#maintenance)
- [Directory Structure](#directory-structure)

---

## Architecture

```
Request → Nginx/Apache → public/index.php → init.php (bootstrap)
  ├── Config::boot()          Load .env
  ├── SecurityHeaders::send() Set CSP, HSTS, etc.
  ├── RequestLogger::log()    Log request start
  └── router.php              Dispatch to controller
       ├── CSRF validation
       ├── Auth guard
       ├── RBAC role check
       ├── ABAC permission check
       └── Controller::method()
            ├── Validator::validate()
            ├── Repository (PDO Singleton)
            └── render() / ApiResponse::success()
```

### Design Principles

- **Defense-in-Depth**: Multiple overlapping security layers
- **PSR-4 Autoloading**: `App\` namespace mapped to `src/`
- **Convention over Configuration**: Repositories auto-map to tables and entities
- **Fail-Fast**: Required config validated on boot, strict PDO error mode
- **Zero Trust**: Every request authenticated, authorized, and rate-limited

---

## Features

### Security
- ✅ AES-256-GCM authenticated encryption (AEAD)
- ✅ Argon2id password hashing (64MB memory cost)
- ✅ Per-form CSRF tokens with expiry
- ✅ Hardened HTTP headers (CSP, HSTS, COOP, CORP)
- ✅ Session fingerprinting (IP + User-Agent binding)
- ✅ Automatic session regeneration & idle timeout
- ✅ Cloudflare Turnstile bot protection
- ✅ Sliding-window rate limiting
- ✅ Fail2Ban-compatible threat logging
- ✅ Secure file uploads (MIME verification, UUID rename, EXIF stripping)
- ✅ SVG sanitization (XSS prevention)
- ✅ IDOR prevention (ownership verification)

### Performance
- ✅ Singleton PDO connection
- ✅ Generator-based streaming for large result sets
- ✅ Multi-tier caching (Memory → Redis → File)
- ✅ OPcache + JIT configuration
- ✅ Response compression (gzip)
- ✅ ETag + 304 Not Modified support
- ✅ Non-blocking sessions (`session_write_close()`)

### Architecture
- ✅ RBAC + ABAC authorization middleware
- ✅ Attribute-based routing (`#[Route('/path')]`)
- ✅ Normalized API responses (`status`, `data`, `errors`, `meta`)
- ✅ Input validation engine (18+ rules)
- ✅ Background job queue with retry/backoff
- ✅ Circuit breaker for external APIs
- ✅ Database migration system (up/down)
- ✅ Structured JSON logging (PSR-3 / Monolog)
- ✅ Health check endpoints (`/health`, `/ready`)
- ✅ CORS middleware
- ✅ Docker-ready (PHP-FPM + Nginx + MySQL + Redis)

---

## Prerequisites

| Requirement | Version |
|-------------|---------|
| PHP | ≥ 8.2 |
| Composer | ≥ 2.0 |
| MySQL / PostgreSQL | 8.0+ / 16+ |
| Redis | 7+ (optional) |

### Required PHP Extensions

```
pdo_mysql    openssl    mbstring    curl
gd           json       fileinfo   session
```

Optional: `redis`, `imagick`, `apcu`

---

## Installation

### 1. Clone & Install Dependencies

```bash
git clone <repository-url> myproject
cd myproject
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
```

Edit `.env` with your settings. **Generate an encryption key:**

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Paste the output as `APP_KEY` in `.env`.

### 3. Create Database & Run Migrations

```bash
# Create your database manually, then:
composer migrate
# or
php bin/migrate.php up
```

### 4. Set Permissions

```bash
chmod -R 755 storage/ logs/
chown -R www-data:www-data storage/ logs/
```

### 5. Start Development Server

```bash
# Using PHP built-in server:
php -S localhost:8080 -t public/

# Or using Docker:
docker-compose up -d
# App available at http://localhost:8080
```

---

## Configuration

All configuration is managed via `.env`. See `.env.example` for all available keys.

| Key | Description | Default |
|-----|-------------|---------|
| `APP_ENV` | Environment (development/production/testing) | development |
| `APP_DEBUG` | Show debug info | true |
| `APP_KEY` | 64-hex-char encryption key | **(required)** |
| `APP_URL` | Base application URL | http://localhost |
| `DB_HOST` | Database host | 127.0.0.1 |
| `DB_NAME` | Database name | **(required)** |
| `SESSION_IDLE_TIMEOUT` | Auto-logout idle time (seconds) | 1800 |
| `TURNSTILE_SITE_KEY` | Cloudflare Turnstile public key | |
| `RATE_LIMIT_LOGIN` | Max login attempts per window | 5 |

Access configuration in code:

```php
use App\Services\Config;

$debug = Config::isDebug();
$url   = Config::baseUrl();
$host  = Config::get('DB_HOST', '127.0.0.1');
$port  = Config::getInt('DB_PORT', 3306);
```

---

## Database Migrations

Migrations live in `src/Migrations/` as PHP files returning `['up' => '...SQL...', 'down' => '...SQL...']`.

```bash
# Run all pending migrations
php bin/migrate.php up

# Roll back the last migration
php bin/migrate.php down

# Show migration status
php bin/migrate.php status
```

### Creating a Migration

Create a file in `src/Migrations/` with timestamp prefix:

```php
<?php
// src/Migrations/2024_004_create_posts_table.php
return [
    'up' => "
        CREATE TABLE `posts` (
            `post_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `title`   VARCHAR(255) NOT NULL,
            `body`    TEXT NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS `posts`;",
];
```

---

## Request Lifecycle

1. `public/index.php` → requires `src/init.php`
2. **Config** → `.env` loaded via Dotenv
3. **Error handler** → exceptions logged, clean 500 page in production
4. **Session** → secure init, idle timeout, periodic regeneration
5. **Security headers** → CSP, HSTS, X-Frame-Options sent
6. **Request logging** → method, path, timing captured
7. **Router** → `#[Route]` attributes scanned on controllers
8. **CSRF** → validated on POST/PUT/PATCH/DELETE
9. **Auth guard** → session checked if `authRequired: true`
10. **RBAC/ABAC** → roles and permissions verified
11. **Controller** → business logic executed
12. **View/API** → HTML rendered or JSON returned

---

## Security

See [SECURITY.md](SECURITY.md) for the full security operations runbook.

### Quick Reference

```php
// Encrypt/decrypt sensitive data
$enc = new Encryption();
$cipher = $enc->encrypt('sensitive-data');
$plain  = $enc->decrypt($cipher);

// Hash passwords
$hash = PasswordHasher::hash($password);
$ok   = PasswordHasher::verify($password, $hash);

// CSRF protection (in forms)
<?= Csrf::inputField() ?>

// CSRF for AJAX
headers: { 'X-CSRF-Token': '<?= Csrf::getToken() ?>' }

// Escape output
<?= Validator::escape($userInput) ?>
```

---

## Authentication

The `UserController` provides a complete authentication flow:

```php
// Routes:
#[Route('/login', methods: ['GET'])]     // Display login form
#[Route('/login', methods: ['POST'])]    // Process login
#[Route('/logout', methods: ['POST'])]   // Logout
#[Route('/dashboard', authRequired: true)] // Protected page
```

Login process:
1. Rate limit check (5 attempts/minute)
2. Cloudflare Turnstile verification
3. Input validation
4. Email lookup + Argon2id password verification
5. Transparent password rehash if needed
6. Session regeneration + CSRF refresh
7. Session fingerprinting (IP + UA)

---

## Authorization (RBAC/ABAC)

### Route-Level Authorization

```php
// Require authentication
#[Route('/profile', authRequired: true)]

// Require specific role
#[Route('/admin', roles: ['admin'])]

// Require specific permission
#[Route('/posts/create', permissions: ['posts.write'])]

// Combine
#[Route('/users/delete', methods: ['DELETE'], authRequired: true, roles: ['admin'])]
```

### Code-Level Authorization

```php
use App\Services\Authorization;

// Check role
if (Authorization::hasRole('editor')) { ... }

// Check permission
if (Authorization::can('posts.delete')) { ... }

// Check ownership (IDOR prevention)
if (Authorization::canAccess($post->getUserId())) { ... }

// Guard methods (throw 403)
Authorization::requireRole('admin');
Authorization::requirePermission('users.write');
```

---

## API Development

### Response Format

```php
use App\Services\ApiResponse;

// Success (200)
ApiResponse::success(['user' => $user]);

// Created (201)
ApiResponse::created(['id' => $newId]);

// Error (422)
ApiResponse::error(['Invalid email address.'], 422);

// Paginated
ApiResponse::paginated($items, $total, $page, $perPage);

// No Content (204)
ApiResponse::noContent();
```

All responses follow this structure:
```json
{
    "status": "success|error",
    "data": {},
    "errors": null,
    "meta": { "pagination": { ... } }
}
```

### Input Validation

```php
$errors = Validator::validate($_POST, [
    'email'    => ['required', 'email', 'max:255'],
    'password' => ['required', 'min:8', 'confirmed'],
    'role'     => ['in:user,editor,admin'],
    'website'  => ['url'],
    'slug'     => ['slug'],
], [
    'email.required' => 'Email is mandatory.',
]);

if (!empty($errors)) {
    ApiResponse::error($errors, 422);
}
```

---

## File Uploads

```php
use App\Services\FileUpload;
use App\Services\ImageProcessor;

$upload = new FileUpload();
$result = $upload->store($_FILES['avatar']);
// $result['name'] = 'a1b2c3d4...f0.jpg'
// $result['path'] = '/storage/uploads/a1b2c3d4...f0.jpg'

// Strip EXIF metadata
ImageProcessor::sanitize($result['path']);

// Generate thumbnail
ImageProcessor::thumbnail($result['path'], 300, 300);
```

---

## Caching

```php
use App\Services\Cache;

// Set/get
Cache::set('user:42', $userData, 3600);
$user = Cache::get('user:42');

// Remember pattern
$result = Cache::remember('expensive:query', 600, function() {
    return $db->heavyQuery();
});

// Delete
Cache::delete('user:42');
Cache::flush();
```

---

## Background Jobs

```php
use App\Services\JobQueue;

// Enqueue
JobQueue::dispatch('send_email', [
    'to'      => 'user@example.com',
    'subject' => 'Welcome!',
    'body'    => 'Your account is active.',
]);

// Delayed job (run in 5 minutes)
JobQueue::dispatch('send_reminder', $payload, delay: 300);
```

Run the worker:
```bash
php bin/worker.php              # Run indefinitely
php bin/worker.php --max=100    # Process 100 jobs then exit
```

---

## Cloudflare Turnstile

### 1. Get Credentials

Register at [Cloudflare Dashboard](https://dash.cloudflare.com/turnstile) and obtain your Site Key + Secret Key.

### 2. Configure

```env
TURNSTILE_SITE_KEY=0x4AAAAAAA...
TURNSTILE_SECRET_KEY=0x4AAAAAAA...
```

### 3. Add Widget to Forms

```php
<form method="POST" action="/login">
    <?= Csrf::inputField() ?>
    <!-- form fields -->
    <?php include __DIR__ . '/../includes/turnstile.php'; ?>
    <button type="submit">Login</button>
</form>
```

### 4. Verify Server-Side

```php
$result = Turnstile::verify($_POST['cf-turnstile-response']);
if ($result->failed()) {
    // Reject the request
}
```

---

## Docker Deployment

```bash
# Build and start all services
docker-compose up -d

# Services:
#   app     — PHP 8.2 FPM (port 9000)
#   web     — Nginx (port 8080)
#   db      — MySQL 8.0 (port 3306)
#   redis   — Redis 7 (port 6379)
#   mailpit — Mail catcher (SMTP 1025, UI 8025)

# View logs
docker-compose logs -f app

# Run migrations inside container
docker-compose exec app php bin/migrate.php up

# Run tests
docker-compose exec app composer test
```

---

## Web Server Configuration

### Nginx

See `docker/nginx/default.conf` for the production-ready configuration.

Key rules:
- Document root: `public/`
- Deny access to: `.env`, `vendor/`, `src/`, `bin/`, `logs/`, `storage/`
- Block PHP execution in `storage/`
- Gzip compression enabled
- Static assets cached for 1 year

### Apache

The `public/.htaccess` includes:
- URL rewriting (all routes → `index.php`)
- Security headers
- Gzip compression
- Browser caching rules
- HTTPS redirect (uncomment in production)

**Critical**: Ensure `AllowOverride All` is set in your Apache vhost.

---

## Testing

```bash
# Run all tests
composer test

# Run specific suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration

# Static analysis (level 6)
composer analyse

# Security audit
composer audit
```

---

## Maintenance

### Regular Tasks

| Task | Command | Frequency |
|------|---------|-----------|
| Dependency audit | `composer audit` | Every build |
| Update dependencies | `composer update` | Monthly |
| Rotate APP_KEY | Manual (see SECURITY.md) | Bi-annually |
| Review security logs | `tail logs/security-threats.log` | Weekly |
| Clear expired cache | `Cache::flush()` | As needed |
| Prune old job records | SQL cleanup | Monthly |

### Health Checks

```bash
# Application health
curl http://localhost:8080/health

# Readiness probe
curl http://localhost:8080/ready
```

---

## Directory Structure

```
project-root/
├── bin/                        # CLI scripts
│   ├── migrate.php             # Database migrations
│   └── worker.php              # Job queue worker
├── docker/                     # Docker configuration
│   └── nginx/default.conf      # Nginx vhost config
├── logs/                       # Application logs (gitignored)
├── public/                     # Web root (document root)
│   ├── .htaccess               # Apache rewrite rules
│   ├── index.php               # Entry point
│   ├── assets/css/             # Stylesheets
│   ├── assets/js/              # JavaScript
│   ├── robots.txt
│   └── sitemap.xml
├── src/                        # Application source (App\ namespace)
│   ├── Abstracts/              # Abstract base classes
│   │   ├── AbstractController  # View rendering, redirects
│   │   └── AbstractRepository  # CRUD, transactions, streaming
│   ├── Controllers/            # Request handlers
│   │   ├── FileController      # Secure file serving
│   │   ├── HealthController    # Health/readiness endpoints
│   │   ├── HomeController      # Public pages, error pages
│   │   └── UserController      # Authentication flows
│   ├── Entities/               # Data models
│   │   └── User
│   ├── Middleware/              # Request/response middleware
│   │   ├── Cors                # Cross-origin resource sharing
│   │   ├── RateLimiter         # Sliding-window rate limiting
│   │   ├── RequestLogger       # Request timing/logging
│   │   ├── SecurityHeaders     # CSP, HSTS, etc.
│   │   └── ThreatLogger        # Fail2Ban-compatible logging
│   ├── Migrations/             # Database migration files
│   ├── Repositories/           # Data access layer
│   │   └── UserRepository
│   ├── Services/               # Core services
│   │   ├── ApiResponse         # Normalized JSON responses
│   │   ├── Authorization       # RBAC/ABAC engine
│   │   ├── Cache               # Multi-tier caching
│   │   ├── CircuitBreaker      # External API resilience
│   │   ├── Config              # Environment configuration
│   │   ├── ConfigRouter        # Request helpers
│   │   ├── Csrf                # CSRF token management
│   │   ├── Database            # Singleton PDO connection
│   │   ├── Encryption          # AES-256-GCM encryption
│   │   ├── FileUpload          # Secure file handling
│   │   ├── Hydration           # Entity auto-hydration trait
│   │   ├── ImageProcessor      # Image sanitization/thumbnails
│   │   ├── JobQueue            # Background job processing
│   │   ├── Logger              # Structured logging (Monolog)
│   │   ├── Mail                # Email service (PHPMailer)
│   │   ├── Migrator            # Migration engine
│   │   ├── PasswordHasher      # Argon2id hashing
│   │   ├── ResponseCompressor  # Gzip + ETag
│   │   ├── Route               # Route attribute definition
│   │   ├── Turnstile           # Cloudflare bot protection
│   │   └── Validator           # Input validation engine
│   ├── Views/                  # PHP templates
│   └── init.php                # Application bootstrap
├── storage/                    # Uploads & cache (gitignored)
│   ├── cache/
│   └── uploads/
├── tests/                      # PHPUnit test suite
│   ├── Unit/
│   └── bootstrap.php
├── .env.example                # Environment template
├── .gitignore
├── composer.json
├── docker-compose.yml
├── Dockerfile
├── opcache.ini
├── phpstan.neon
├── phpunit.xml
├── readme.md                   # This file
└── SECURITY.md                 # Security operations runbook
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.
