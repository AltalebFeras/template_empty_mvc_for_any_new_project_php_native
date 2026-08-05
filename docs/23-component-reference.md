# Component Reference & Technical Usage Guide

This document provides a comprehensive technical breakdown of the core controllers, middleware, migration files, services, and CLI scripts available in this native PHP MVC application framework.

Each component section includes:
1. **How It Works**: Internal architecture, security mechanisms, state management, and execution details.
2. **How to Use It**: Class/method signatures, parameter descriptions, practical code snippets, or CLI commands.

---

## Table of Contents

- [Controllers](#controllers)
  - [FileController](#filecontroller)
  - [HealthController](#healthcontroller)
- [Middleware](#middleware)
  - [Cors](#cors)
  - [RateLimiter](#ratelimiter)
  - [RequestLogger](#requestlogger)
  - [SecurityHeaders](#securityheaders)
  - [ThreatLogger](#threatlogger)
- [Migrations](#migrations)
  - [2024_001_create_users_table](#2024_001_create_users_table)
  - [2024_002_create_roles_permissions_tables](#2024_002_create_roles_permissions_tables)
  - [2024_003_create_jobs_table](#2024_003_create_jobs_table)
- [Services](#services)
  - [ApiResponse](#apiresponse)
  - [Authorization](#authorization)
  - [Cache](#cache)
  - [CircuitBreaker](#circuitbreaker)
  - [Config](#config)
  - [ConfigRouter](#configrouter)
  - [Csrf](#csrf)
  - [Database](#database)
  - [Encryption](#encryption)
  - [FileUpload](#fileupload)
  - [Hydration](#hydration)
  - [ImageProcessor](#imageprocessor)
  - [JobQueue](#jobqueue)
  - [Logger](#logger)
  - [Mail](#mail)
  - [Migrator](#migrator)
  - [PasswordHasher](#passwordhasher)
  - [ResponseCompressor](#responsecompressor)
  - [Route](#route)
  - [router (Route Dispatcher)](#router-route-dispatcher)
  - [Turnstile](#turnstile)
  - [Validator](#validator)
- [CLI Scripts](#cli-scripts)
  - [bin/migrate.php](#binmigratephp)
  - [bin/worker.php](#binworkerphp)

---

## Controllers

### FileController
File: [`src/Controllers/FileController.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Controllers/FileController.php)

#### How It Works
`FileController` serves stored user uploads securely from `/storage/uploads/`, which is kept outside the web document root (`public/`).
- **Validation**: Strict regex matching (`/^[a-f0-9]{32}\.[a-z0-9]+$/`) verifies that requested filenames are 32-character hexadecimal UUIDs with a valid extension.
- **Path Traversal Protection**: Uses `realpath()` to resolve the real target path and validates that it begins with the canonical upload directory path.
- **MIME & Headers**: Uses PHP `finfo` magic-byte inspection to dynamically identify the MIME type. Images and videos are delivered inline (`Content-Disposition: inline`), while documents force download (`Content-Disposition: attachment`). Sets `X-Content-Type-Options: nosniff` and `Cache-Control: private, max-age=3600`.

#### How to Use It
The endpoint is automatically mapped by attribute routing:
```http
GET /file?name=a1b2c3d4e5f67890a1b2c3d4e5f67890.jpg
```

**PHP Usage Example** (generating a downloadable file URL in views):
```php
$fileUrl = Config::baseUrl() . '/file?name=' . urlencode($user->getAvatarFilename());
echo '<img src="' . htmlspecialchars($fileUrl) . '" alt="Avatar">';
```

---

### HealthController
File: [`src/Controllers/HealthController.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Controllers/HealthController.php)

#### How It Works
Provides operational telemetry endpoints for container orchestrators (Docker, Kubernetes, load balancers):
- `GET /health`: Runs dependency diagnostic checks for MySQL (`Database::isHealthy()`) and Redis (PONG test via `Predis`). Returns HTTP 200 with status `"healthy"` if all checks pass, or HTTP 503 with status `"degraded"` if any dependency fails.
- `GET /ready`: A fast readiness probe returning HTTP 200 when the database connection is established, or HTTP 503 when degraded.

#### How to Use It
**cURL Probe Example**:
```bash
# General application health check
curl -i http://localhost:8080/health

# Kubernetes/Load Balancer readiness probe
curl -i http://localhost:8080/ready
```

---

## Middleware

### Cors
File: [`src/Middleware/Cors.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Middleware/Cors.php)

#### How It Works
Implements Cross-Origin Resource Sharing controls for cross-domain API clients:
- Checks incoming `HTTP_ORIGIN` headers against an allowed whitelist (defaults to `Config::baseUrl()`).
- Sets CORS response headers (`Access-Control-Allow-Origin`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, `Access-Control-Allow-Credentials`, `Access-Control-Max-Age`).
- Intercepts preflight HTTP `OPTIONS` requests and responds immediately with HTTP 204 (or HTTP 403 if origin is rejected).

#### How to Use It
Call `Cors::handle()` early during application bootstrapping (e.g., in `init.php` or `router.php`):
```php
use App\Middleware\Cors;

// Standard usage (uses APP_URL)
Cors::handle();

// Custom multi-origin API setup
Cors::handle(
    allowedOrigins: ['https://frontend.example.com', 'https://admin.example.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowCredentials: true,
    maxAge: 86400
);
```

---

### RateLimiter
File: [`src/Middleware/RateLimiter.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Middleware/RateLimiter.php)

#### How It Works
Enforces sliding-window rate limits per client IP or custom identifier:
- **Storage Backend**: Automatically uses Redis sorted sets (`ZADD` / `ZREMRANGEBYSCORE`) if `Predis` is installed and connected. Otherwise, falls back to file-based JSON tracking under `storage/cache/rate_limits/`.
- **Response Headers**: Emits standard rate limit headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.
- **Threat Logging**: Automatically triggers `ThreatLogger::log('rate_limit')` when a client exceeds thresholds.

#### How to Use It
```php
use App\Middleware\RateLimiter;

// 1. Check rate limit manually (returns bool)
if (!RateLimiter::check(action: 'login', maxAttempts: 5, windowSeconds: 60)) {
    // Client exceeded 5 login attempts per minute
}

// 2. Enforce limit (automatically emits HTTP 429 JSON response and exits if exceeded)
RateLimiter::enforce(action: 'api', maxAttempts: 60, windowSeconds: 60);
```

---

### RequestLogger
File: [`src/Middleware/RequestLogger.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Middleware/RequestLogger.php)

#### How It Works
Measures and logs incoming HTTP request performance metrics:
- Captures start timestamp (`microtime(true)`) upon execution.
- Registers a shutdown function (`register_shutdown_function`) to record completion metrics after response processing finishes.
- Ignores static web assets (`.css`, `.js`, `.jpg`, `.png`, `.svg`, etc.).
- Outputs structured log entries containing HTTP method, path, final HTTP status code, total execution duration in milliseconds, and peak memory usage in MB.

#### How to Use It
Call `RequestLogger::log()` near the top of `init.php`:
```php
use App\Middleware\RequestLogger;

RequestLogger::log();
```

---

### SecurityHeaders
File: [`src/Middleware/SecurityHeaders.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Middleware/SecurityHeaders.php)

#### How It Works
Sends security-hardening HTTP headers on every request:
- `X-Content-Type-Options: nosniff` (prevents MIME sniffing)
- `X-Frame-Options: SAMEORIGIN` (blocks clickjacking attacks)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`: disables unused browser API capabilities (`geolocation`, `microphone`, `camera`, `payment`, `usb`)
- Removes `X-Powered-By` header to hide PHP versions
- Enables Strict-Transport-Security (`HSTS`) in production over HTTPS
- Emits a strict Content Security Policy (`CSP`) supporting Cloudflare Turnstile iframes and Google Fonts

#### How to Use It
Call `SecurityHeaders::send()` in `init.php`:
```php
use App\Middleware\SecurityHeaders;

SecurityHeaders::send();
```

---

### ThreatLogger
File: [`src/Middleware/ThreatLogger.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Middleware/ThreatLogger.php)

#### How It Works
Generates security audit records compatible with Fail2Ban, Cloudflare WAF, and intrusion detection systems:
- Appends threat log entries to `logs/security-threats.log`.
- Log Format: `[ISO8601_TIMESTAMP] THREAT type=... ip=... key=value`
- Automatic PII Masking: Emails (`f***@example.com`) and sensitive context variables are redacted prior to writing.

#### How to Use It
```php
use App\Middleware\ThreatLogger;

// Log generic security threat
ThreatLogger::log('csrf_violation', $ip, ['path' => '/login']);

// Log brute force detection
ThreatLogger::bruteForce(ip: $clientIp, action: 'login', attempts: 10);

// Log malicious payload attempt
ThreatLogger::maliciousPayload(ip: $clientIp, path: '/api/v1/user', detail: 'SQL injection string in query');
```

---

## Migrations

### 2024_001_create_users_table
File: [`src/Migrations/2024_001_create_users_table.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Migrations/2024_001_create_users_table.php)

#### How It Works
Defines the `users` table schema:
- `user_id`: Auto-incrementing unsigned primary key.
- `first_name`, `last_name`: `VARCHAR(100)`.
- `email`: `VARCHAR(255)` unique indexed string.
- `password`: `VARCHAR(255)` formatted for Argon2id hashes.
- `is_activated`: `TINYINT(1)` flag.
- `role_id`: `INT UNSIGNED` foreign key column (default 1).
- `created_at`, `updated_at`: `DATETIME` timestamps.

#### How to Use It
Executed automatically by the migration engine:
```bash
php bin/migrate.php up
```

---

### 2024_002_create_roles_permissions_tables
File: [`src/Migrations/2024_002_create_roles_permissions_tables.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Migrations/2024_002_create_roles_permissions_tables.php)

#### How It Works
Creates the Role-Based Access Control (RBAC) database structures:
- `roles` table: Primary key `role_id`, unique `name`. Inserts default role records: `1 => user`, `2 => editor`, `3 => admin`.
- `permissions` table: Primary key `permission_id`, unique permission strings (e.g. `users.read`, `posts.write`).
- `role_permissions` table: Junction table mapping roles to permission capabilities with cascading deletions.
- Alters `users` table to add foreign key constraint linking `users.role_id` to `roles.role_id`.

#### How to Use It
Executed via CLI migration runner:
```bash
php bin/migrate.php up
```

---

### 2024_003_create_jobs_table
File: [`src/Migrations/2024_003_create_jobs_table.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Migrations/2024_003_create_jobs_table.php)

#### How It Works
Creates the SQL table for asynchronous background queue tasks:
- `id`: `BIGINT UNSIGNED` primary key.
- `type`: Job name identifier string (e.g., `send_email`).
- `payload`: `JSON` column storing parameter payloads.
- `status`: `ENUM('pending', 'processing', 'completed', 'failed')`.
- `attempts`: Retry counter (`TINYINT UNSIGNED`).
- `error`: Exception text string when job failures occur.
- `run_at`: Schedule timestamp for delayed job dispatching.
- Composite index on `(status, run_at)` for high-performance lock queries (`FOR UPDATE SKIP LOCKED`).

#### How to Use It
Run migration:
```bash
php bin/migrate.php up
```

---

## Services

### ApiResponse
File: [`src/Services/ApiResponse.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/ApiResponse.php)

#### How It Works
Standardizes all REST API JSON outputs to adhere to a structured schema:
```json
{
  "status": "success|error",
  "data": mixed,
  "errors": array|null,
  "meta": object|null
}
```
Sets `Content-Type: application/json; charset=utf-8` and `Cache-Control: no-store`, encodes response data, emits HTTP status code, and halts execution immediately (`exit`).

#### How to Use It
```php
use App\Services\ApiResponse;

// Standard Success Response (200 OK)
ApiResponse::success(['user' => $userData]);

// Resource Created (201 Created)
ApiResponse::created(['id' => $newUserId]);

// Validation Error (422 Unprocessable Entity)
ApiResponse::error(['email' => 'Invalid email address.'], 422);

// Paginated Dataset Response
ApiResponse::paginated(items: $usersList, total: 150, page: 1, perPage: 15);

// No Content Response (204 No Content)
ApiResponse::noContent();
```

---

### Authorization
File: [`src/Services/Authorization.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Authorization.php)

#### How It Works
Lightweight RBAC & ABAC engine:
- **Role Hierarchy**: `admin` (level 3) > `editor` (level 2) > `user` (level 1) > `guest` (level 0). Higher roles inherit permissions from lower levels.
- **Wildcard Permissions**: Supports exact permissions (`posts.write`), domain wildcards (`users.*`), and root admin wildcards (`*`).
- **ABAC / Ownership**: `owns($resourceUserId)` verifies resource owner matching against `$_SESSION['user_id']`.

#### How to Use It
```php
use App\Services\Authorization;

// Check role level
if (Authorization::hasRole('editor')) {
    // Current user is editor or admin
}

// Permission check
if (Authorization::can('posts.delete')) {
    // Delete allowed
}

// Ownership check (IDOR Prevention)
if (!Authorization::canAccess($article->getUserId())) {
    http_response_code(403);
    exit('Forbidden');
}

// Require role or throw 403 RuntimeException
Authorization::requireRole('admin');
Authorization::requirePermission('users.write');
```

---

### Cache
File: [`src/Services/Cache.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Cache.php)

#### How It Works
Provides multi-tier caching with zero configuration:
- **L1 Cache**: In-memory static PHP array (request-scoped, zero-latency).
- **L2 Cache**: Redis connection via `Predis\Client` if installed.
- **L3 Cache**: File-based cache under `storage/cache/` using MD5 filename hashing with JSON expiration timestamps.

#### How to Use It
```php
use App\Services\Cache;

// Put & Get
Cache::set('user:100', $userArray, ttl: 3600);
$user = Cache::get('user:100');

// Remember pattern (fetch from cache or calculate & cache closure output)
$stats = Cache::remember('dashboard_stats', ttl: 600, compute: function() {
    return $dbRepository->calculateHeavyStats();
});

// Delete & Flush
Cache::delete('user:100');
Cache::flush();
```

---

### CircuitBreaker
File: [`src/Services/CircuitBreaker.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/CircuitBreaker.php)

#### How It Works
Prevents cascading system outages when external integrations (Payment APIs, SMS gateways) fail:
- **States**:
  - `CLOSED`: Normal operation; calls pass through.
  - `OPEN`: Service unavailable; requests immediately execute fallback or throw exception without calling remote service.
  - `HALF_OPEN`: Trial period after recovery timeout; allows limited attempts to check if service recovered.
- State transitions and failure counts are persisted in `storage/cache/circuit_breaker/`.

#### How to Use It
```php
use App\Services\CircuitBreaker;

$cb = new CircuitBreaker(service: 'payment_gateway', failureThreshold: 5, recoveryTimeout: 60);

$result = $cb->call(
    operation: fn() => $paymentApi->charge($amount),
    fallback: fn() => ['status' => 'failed', 'message' => 'Payment service temporarily unavailable']
);
```

---

### Config
File: [`src/Services/Config.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Config.php)

#### How It Works
Singleton configuration manager built on `vlucas/phpdotenv`:
- Reads `.env` from application root.
- Enforces presence of essential environment variables (`APP_ENV`, `APP_KEY`, `APP_URL`, `DB_HOST`, `DB_NAME`, `DB_USER`) during boot, failing fast on misconfiguration.
- Provides typed getters and security methods (`getEncryptionKey()`, `isProduction()`, `baseUrl()`).

#### How to Use It
```php
use App\Services\Config;

// Boot (idempotent, called in init.php)
Config::boot();

$dbHost = Config::get('DB_HOST', '127.0.0.1');
$debug  = Config::getBool('APP_DEBUG', false);
$port   = Config::getInt('DB_PORT', 3306);
$isProd = Config::isProduction();
$key    = Config::getEncryptionKey(); // 32 raw binary bytes decoded from hex
```

---

### ConfigRouter
File: [`src/Services/ConfigRouter.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/ConfigRouter.php)

#### How It Works
HTTP request utility provider:
- **Method Spoofing**: `getMethod()` checks `$_POST['_method']` (e.g. `DELETE`, `PUT`) when handling HTML forms.
- **Session Fingerprinting**: `checkOriginConnection()` compares `$_SESSION['ip_address']` and `$_SESSION['user_agent']` against incoming request headers, invalidating session if hijacked.
- **IP Detection**: `getClientIp()` inspects proxy headers (`HTTP_CLIENT_IP`, `HTTP_X_FORWARDED_FOR`, `REMOTE_ADDR`).

#### How to Use It
```php
use App\Services\ConfigRouter;

$method   = ConfigRouter::getMethod();   // 'GET', 'POST', 'DELETE', etc.
$clientIp = ConfigRouter::getClientIp(); // Validated IPv4/IPv6 address
$isAjax   = ConfigRouter::isAjax();     // bool
$isHttps  = ConfigRouter::isHttps();    // bool

// Secure session origin validation
if (!ConfigRouter::checkOriginConnection()) {
    ConfigRouter::redirect(Config::baseUrl() . '/login');
}
```

---

### Csrf
File: [`src/Services/Csrf.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Csrf.php)

#### How It Works
Protects against Cross-Site Request Forgery:
- Generates cryptographically secure 32-byte pseudo-random tokens.
- Supports per-form token scoping and configurable expiration windows.
- Validates tokens using constant-time string comparison (`hash_equals`) to prevent timing attacks.

#### How to Use It
**In HTML Forms**:
```html
<form method="POST" action="/profile">
    <?= App\Services\Csrf::inputField('profile_form') ?>
    <input type="text" name="display_name">
    <button type="submit">Save</button>
</form>
```

**In JavaScript / AJAX Requests**:
```javascript
fetch('/api/v1/update', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= App\Services\Csrf::getToken() ?>'
    },
    body: JSON.stringify({ name: 'John' })
});
```

**Server-Side Validation**:
```php
use App\Services\Csrf;

if (!Csrf::validateToken($_POST['_csrf_token'] ?? '', $_POST['_csrf_form_id'] ?? null)) {
    http_response_code(403);
    exit('CSRF token validation failed.');
}
```

---

### Database
File: [`src/Services/Database.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Database.php)

#### How It Works
Singleton PDO database manager:
- Connects to MySQL or PostgreSQL using credentials from `Config`.
- Configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`, and `PDO::ATTR_EMULATE_PREPARES => false`.
- Contains helper method `isHealthy()` to run lightweight connectivity queries (`SELECT 1`).

#### How to Use It
```php
use App\Services\Database;

// Get PDO instance singleton
$pdo = Database::getInstance();

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();
```

---

### Encryption
File: [`src/Services/Encryption.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Encryption.php)

#### How It Works
Provides authenticated symmetric encryption using **AES-256-GCM** (AEAD):
- Generates a unique 12-byte initialization vector (IV) per encryption operation via `random_bytes()`.
- Captures a 16-byte authentication tag during encryption to protect payload integrity against tampering.
- Encodes output as `base64(IV + Tag + Ciphertext)`.

#### How to Use It
```php
use App\Services\Encryption;

$enc = new Encryption();

// Encrypt plaintext string
$ciphertext = $enc->encrypt('secret API key');

// Decrypt ciphertext string
$plaintext = $enc->decrypt($ciphertext);
```

---

### FileUpload
File: [`src/Services/FileUpload.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/FileUpload.php)

#### How It Works
Enforces file upload security controls:
- **Magic-Byte Inspection**: Uses `finfo` to verify real file MIME type against whitelist (`image/jpeg`, `image/png`, `image/webp`, `image/gif`, `video/mp4`, `video/webm`, `application/pdf`).
- **Filename Sanitization**: Replaces user-provided file names with a randomly generated 32-character hex UUID.
- **Memory Protection**: Estimates image memory footprint (`width * height * 4 * 1.7`) prior to processing to prevent decompression bomb Denial-of-Service attacks.
- Moves files outside document root to `storage/uploads/`.

#### How to Use It
```php
use App\Services\FileUpload;

$uploader = new FileUpload();

try {
    $fileInfo = $uploader->store($_FILES['avatar']);
    // Returns array: ['path' => '...', 'name' => '...', 'original_name' => '...', 'mime' => '...', 'size' => ...]
} catch (\RuntimeException $e) {
    // Handle validation failure
}
```

---

### Hydration
File: [`src/Services/Hydration.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Hydration.php)

#### How It Works
A PHP trait included in Entity models:
- Translates database array column names (`snake_case`) into entity setter method names (`setCamelCase`).
- Implements custom `__serialize()` and `__unserialize()` methods for session storage or caching.

#### How to Use It
```php
namespace App\Entities;

use App\Services\Hydration;

class User
{
    use Hydration;

    private int $userId;
    private string $email;

    public function setUserId(int $id): void { $this->userId = $id; }
    public function setEmail(string $email): void { $this->email = $email; }
    
    public function getUserId(): int { return $this->userId; }
    public function getEmail(): string { return $this->email; }
}

// Auto-hydrate entity from DB array
$user = new User([
    'user_id' => 42,
    'email'   => 'jane@example.com',
]);
```

---

### ImageProcessor
File: [`src/Services/ImageProcessor.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/ImageProcessor.php)

#### How It Works
Image manipulation and sanitization utility:
- **EXIF Stripping**: Re-encodes uploaded images (JPEG, PNG, WebP) to remove embedded GPS coordinates and camera metadata.
- **Thumbnail Generation**: Resizes images preserving original aspect ratio.
- **SVG Sanitization**: Strips dangerous XML tags (`<script>`, `<foreignObject>`, inline JS event attributes `onload`, `onerror`) to prevent SVG XSS vulnerabilities.

#### How to Use It
```php
use App\Services\ImageProcessor;

// Strip EXIF data for privacy
ImageProcessor::sanitize('/path/to/uploaded/image.jpg');

// Create 300x300 thumbnail preserving aspect ratio
ImageProcessor::thumbnail('/path/to/image.jpg', width: 300, height: 300);

// Sanitize uploaded SVG file
ImageProcessor::sanitizeSvg('/path/to/uploaded/vector.svg');
```

---

### JobQueue
File: [`src/Services/JobQueue.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/JobQueue.php)

#### How It Works
Database-backed task queue service:
- `dispatch()` inserts job records into the `jobs` database table with JSON payload data and execution timestamps (`run_at`).
- Worker queries available pending jobs using `SELECT ... FOR UPDATE SKIP LOCKED` for atomic lock acquisition without worker blocking.
- Implements exponential backoff retry policy (`30s * 2^attempts`) up to 3 attempts.

#### How to Use It
```php
use App\Services\JobQueue;

// Dispatch background job immediately
JobQueue::dispatch('send_email', [
    'to'      => 'user@example.com',
    'subject' => 'Welcome!',
    'body'    => 'Thank you for registering.',
]);

// Dispatch job delayed by 300 seconds (5 minutes)
JobQueue::dispatch('send_reminder', ['user_id' => 123], delay: 300);
```

---

### Logger
File: [`src/Services/Logger.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Logger.php)

#### How It Works
Monolog logging wrapper exposing configured logging channels:
- Channels: `app` (`logs/app.log`), `security` (`logs/security.log`), `db` (`logs/db.log`).
- Formats log entries as structured JSON lines.
- Adjusts log levels dynamically based on `APP_DEBUG` (DEBUG in dev, INFO/WARNING in production).

#### How to Use It
```php
use App\Services\Logger;

Logger::channel('app')->info('User profile updated', ['user_id' => 42]);
Logger::channel('security')->warning('Failed login attempt', ['ip' => $clientIp]);
Logger::channel('db')->error('Query failure', ['sql' => $sqlQuery]);
```

---

### Mail
File: [`src/Services/Mail.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Mail.php)

#### How It Works
SMTP mail dispatch engine wrapping PHPMailer:
- Configures SMTP host, port, authentication, and TLS/STARTTLS encryption from `.env`.
- Supports HTML email rendering, plain-text alternative fallbacks, and file attachments.

#### How to Use It
```php
use App\Services\Mail;

$mailer = new Mail();

$success = $mailer->sendEmail(
    fromEmail: 'noreply@example.com',
    fromName:  'My Application',
    toEmail:   'recipient@example.com',
    toName:    'John Doe',
    subject:   'Welcome to our platform',
    htmlBody:  '<h1>Welcome!</h1><p>Your account is ready.</p>'
);
```

---

### Migrator
File: [`src/Services/Migrator.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Migrator.php)

#### How It Works
Database migration execution engine:
- Scans `src/Migrations/*.php` files in alphabetical/timestamp order.
- Tracks completed migrations in database table `_migrations`.
- Executes migration SQL queries inside transactional blocks (`beginTransaction` / `commit` / `rollBack`).

#### How to Use It
```php
use App\Services\Migrator;

$migrator = new Migrator();

// Apply pending migrations
$migrator->up();

// Rollback last applied migration batch
$migrator->down();

// Output status table of migrations
$migrator->status();
```

---

### PasswordHasher
File: [`src/Services/PasswordHasher.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/PasswordHasher.php)

#### How It Works
Handles password security using **Argon2id**:
- Uses memory cost of 64MB (`65536` KB), time cost of `4` iterations, and parallelism factor of `2` threads.
- `verify()` checks plaintext passwords against stored hashes.
- `needsRehash()` detects if algorithmic costs need upgrading.

#### How to Use It
```php
use App\Services\PasswordHasher;

// Hash new user password
$hash = PasswordHasher::hash('user-secret-password');

// Verify credentials during login
if (PasswordHasher::verify($inputPassword, $storedHash)) {
    // Rehash if security parameters updated
    if (PasswordHasher::needsRehash($storedHash)) {
        $newHash = PasswordHasher::hash($inputPassword);
        $userRepository->updatePassword($userId, $newHash);
    }
}
```

---

### ResponseCompressor
File: [`src/Services/ResponseCompressor.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/ResponseCompressor.php)

#### How It Works
HTTP response optimization service:
- Generates an `ETag` checksum from output content. If the client sends a matching `If-None-Match` header, emits `304 Not Modified` and terminates output.
- Activates transparent gzip response compression if supported by the browser client (`Accept-Encoding: gzip`).

#### How to Use It
Call `ResponseCompressor::start()` early during HTTP initialization:
```php
use App\Services\ResponseCompressor;

ResponseCompressor::start();
```

---

### Route
File: [`src/Services/Route.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Route.php)

#### How It Works
A PHP 8 attribute declaration (`#[Attribute]`) attached to controller methods to declare route rules, allowed HTTP methods, authentication guards, and role/permission requirements.

#### How to Use It
```php
use App\Services\Route;

class PostController
{
    #[Route('/posts', methods: ['GET'])]
    public function index(): void {}

    #[Route('/posts/create', methods: ['POST'], authRequired: true, permissions: ['posts.write'])]
    public function store(): void {}

    #[Route('/admin/settings', methods: ['GET', 'POST'], authRequired: true, roles: ['admin'])]
    public function settings(): void {}
}
```

---

### router (Route Dispatcher)
File: [`src/Services/router.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/router.php)

#### How It Works
Reflection-based attribute router:
1. Normalizes target request URI and HTTP method (handling `_method` spoofing).
2. Performs automated state-changing CSRF token validation on `POST`, `PUT`, `PATCH`, and `DELETE` methods.
3. Dynamically scans controller classes in `src/Controllers/` using PHP Reflection.
4. Executes the middleware pipeline in sequential order: Authentication Guard → RBAC Role Check → ABAC Permission Check → Rate Limiter → Controller Action.
5. Emits HTTP 404 page if no matching route is found.

#### How to Use It
Included automatically in `public/index.php`:
```php
require_once __DIR__ . '/../src/Services/router.php';
```

---

### Turnstile
File: [`src/Services/Turnstile.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Turnstile.php)

#### How It Works
Cloudflare Turnstile bot prevention service:
- Sends POST request to `https://challenges.cloudflare.com/turnstile/v0/siteverify` containing secret key and client token (`cf-turnstile-response`).
- Evaluates Cloudflare API response JSON (`success: true|false`).

#### How to Use It
```php
use App\Services\Turnstile;

$token = $_POST['cf-turnstile-response'] ?? '';

$result = Turnstile::verify($token);

if ($result->failed()) {
    http_response_code(400);
    exit('CAPTCHA validation failed. Please try again.');
}
```

---

### Validator
File: [`src/Services/Validator.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Validator.php)

#### How It Works
Input validation engine supporting over 18 rules (`required`, `email`, `min`, `max`, `in`, `numeric`, `integer`, `alpha`, `alphanumeric`, `confirmed`, `url`, `ip`, `json`, `regex`, `slug`, `date`). Supports custom message overrides and includes HTML escaping (`Validator::escape()`).

#### How to Use It
```php
use App\Services\Validator;

$data = [
    'email'    => 'user@example.com',
    'password' => 'secret123',
    'age'      => '25',
];

$errors = Validator::validate($data, [
    'email'    => ['required', 'email', 'max:255'],
    'password' => ['required', 'min:8'],
    'age'      => ['required', 'integer', 'min:18'],
], [
    'email.required' => 'Email address is mandatory.',
]);

if (!empty($errors)) {
    // Return validation errors
    ApiResponse::error($errors, 422);
}

// Escape user input for safe HTML output
$safeHtml = Validator::escape($_POST['comment']);
```

---

## CLI Scripts

### bin/migrate.php
File: [`bin/migrate.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/bin/migrate.php)

#### How It Works
CLI executable script for managing database migrations:
- Boots environment configuration (`Config::boot()`).
- Instantiates `Migrator` service and executes command arguments (`up`, `down`, `status`).

#### How to Use It
```bash
# Execute all pending database migrations
php bin/migrate.php up

# Rollback the last applied migration
php bin/migrate.php down

# Print migration status table
php bin/migrate.php status
```

---

### bin/worker.php
File: [`bin/worker.php`](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/bin/worker.php)

#### How It Works
CLI background job worker runner:
- Boots configuration environment.
- Passes `--max=N` argument flag to `JobQueue::work()` loop to limit total processed jobs before exiting.

#### How to Use It
```bash
# Run worker loop indefinitely
php bin/worker.php

# Run worker processing up to 100 jobs then exit
php bin/worker.php --max=100
```
