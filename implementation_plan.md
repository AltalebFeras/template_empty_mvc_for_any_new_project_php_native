# Enterprise-Grade PHP MVC Template — Full Refactoring Plan

> **Scope**: Audit, harden, optimize, scale, and document the native PHP MVC template at  
> [project root](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native)

---

## Audit Summary — Current State

### What Already Works Well ✅
- Attribute-based routing (`#[Route]`) with auto-discovery
- CSRF Synchronizer Token pattern with `hash_equals()`
- PDO with `ERRMODE_EXCEPTION` and `ATTR_EMULATE_PREPARES = false`
- Session hardening (`use_strict_mode`, `HttpOnly`, `SameSite`)
- XSS output escaping via `Validator::escape()`
- Column-key whitelisting in `AbstractRepository`
- Clean MVC separation (Controllers / Entities / Repositories / Services / Views)

### Critical Security Findings 🔴

| # | Finding | Severity | File |
|---|---------|----------|------|
| 1 | **AES-256-CBC without HMAC** — no authentication tag → padding oracle attack | CRITICAL | [Encrypt_decrypt.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Encrypt_decrypt.php) |
| 2 | **Secrets in PHP constants** — DB creds, mail creds, encryption key hardcoded in [config.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/config.php) (committed risk) | CRITICAL | config.php |
| 3 | **No password hashing enforcement** — no `PASSWORD_ARGON2ID` in any auth flow | HIGH | Missing entirely |
| 4 | **Duplicated router block** — [router.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/router.php) lines 67-107 are an exact copy of lines 26-65 → every route dispatches twice | HIGH | router.php |
| 5 | **Table name injection** — `$this->table` derived from class name via string ops, used unquoted in SQL | MEDIUM | [AbstractRepository.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Abstracts/AbstractRepository.php) |
| 6 | **No rate limiting** — login/register forms wide open to brute force | HIGH | Missing |
| 7 | **No RBAC/ABAC** — `authRequired` boolean is the only gate; no role/permission check | HIGH | Route.php / router.php |
| 8 | **Database creates new PDO per Repository** — no connection reuse/singleton | MEDIUM | Database.php |
| 9 | **CSP & HSTS commented out** — headers present but inactive | MEDIUM | .htaccess |
| 10 | **No Cloudflare Turnstile** — no bot protection on public forms | MEDIUM | Missing |
| 11 | **No structured logging** — only `error_log()` in Mail.php | MEDIUM | All |
| 12 | **No file upload security** — no upload handling exists | LOW (template) | Missing |
| 13 | **Dashboard XSS** — `$_SESSION['firstName']` echoed without escaping in [dashboard.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Views/dashboard/dashboard.php#L8) | MEDIUM | dashboard.php |

---

## User Review Required

> [!IMPORTANT]
> **Breaking changes**: This refactoring replaces `config.php` (hardcoded constants) with a `.env` file + Dotenv loader. Any deployment scripts or documentation referencing `config.php` must be updated. The old `config.php` and `config_example.php` files will be removed.

> [!WARNING]
> **Namespace change**: The current PSR-4 prefix is `src\\` which is unconventional. The plan renames it to `App\\` (standard convention). All `use src\...` statements throughout the codebase will be updated to `use App\...`. This is a find-and-replace operation but touches every PHP file.

> [!IMPORTANT]
> **New dependencies**: This plan adds `vlucas/phpdotenv`, `monolog/monolog`, `predis/predis` (optional), and `phpstan/phpstan` to `composer.json`. These are all MIT-licensed, stable, widely-used packages.

---

## Open Questions

> [!IMPORTANT]
> **Q1: Namespace preference** — Should we rename `src\\` → `App\\` (PHP community standard) or keep `src\\`?  
> **Recommendation**: Rename to `App\\` for PSR-4 compliance and industry convention.

> [!IMPORTANT]  
> **Q2: Redis availability** — Is Redis available in your deployment environments? This impacts caching and rate-limiting implementation. If not, we'll implement file-based/SQL fallbacks.

> [!IMPORTANT]
> **Q3: PHP version target** — The plan assumes PHP 8.2+ (required for `Attribute`, `readonly`, Argon2id). Please confirm your minimum PHP version.

> [!IMPORTANT]
> **Q4: Language preference** — Current views are in French. Should the framework internals (error messages, log messages, code comments) remain in French or switch to English?

---

## Proposed Changes

The changes are organized by component layer, from foundational infrastructure up to application-level features.

---

### Phase 1: Foundation & Configuration

#### [DELETE] [config.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/config.php)
#### [DELETE] [config_example.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/config_example.php)

#### [NEW] `.env.example`
Template environment file with all configuration keys, safe placeholder values, and documentation comments:
```env
APP_ENV=development
APP_DEBUG=true
APP_KEY=  # 32-char random key, generate with: php -r "echo bin2hex(random_bytes(32));"
APP_TIMEZONE=Europe/Paris
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=app
DB_USER=root
DB_PASS=

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@example.com
MAIL_FROM_NAME=App
MAIL_ENCRYPTION=tls

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

SESSION_LIFETIME=7200
SESSION_IDLE_TIMEOUT=1800
```

#### [NEW] `src/Services/Config.php`
Singleton configuration loader using `vlucas/phpdotenv`. Provides typed getters (`Config::get('DB_HOST')`, `Config::getBool('APP_DEBUG')`). Validates required keys on boot. Replaces all `define()` constants.

#### [MODIFY] [composer.json](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/composer.json)
- Rename PSR-4 prefix: `"src\\"` → `"App\\"` (pending Q1 answer)
- Add `require`: `vlucas/phpdotenv ^5.6`, `monolog/monolog ^3.0`, `predis/predis ^2.0` (optional)
- Add `require-dev`: `phpstan/phpstan ^2.0`
- Add `scripts`: `"migrate"`, `"test"`, `"analyse"`

#### [MODIFY] [.gitignore](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/.gitignore)
Add `.env`, `/storage/`, `/logs/`, `/var/`, `phpstan.neon` output, and IDE files.

---

### Phase 2: Security Hardening — Core Services

#### [MODIFY] [Encrypt_decrypt.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Encrypt_decrypt.php) → rename to `Encryption.php`
- **Switch from AES-256-CBC to AES-256-GCM** — provides authenticated encryption (AEAD) eliminating padding oracle attacks entirely.
- Store IV + authentication tag alongside ciphertext.
- Use `OPENSSL_RAW_DATA` flag instead of base64 intermediary.
- Add `encrypt(string $plaintext): string` and `decrypt(string $payload): string|false` with proper tag verification.
- Key derivation via `hash_hkdf()` instead of raw `hash('sha256')`.

#### [NEW] `src/Services/PasswordHasher.php`
- `hash(string $password): string` — uses `PASSWORD_ARGON2ID` with configurable memory/time cost.
- `verify(string $password, string $hash): bool` — constant-time verification.
- `needsRehash(string $hash): bool` — checks if stored hash needs upgrade.

#### [MODIFY] [Csrf.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Csrf.php)
- Add **per-form token** support: `Csrf::getToken(string $formId)` generates/stores scoped tokens.
- Add token expiration (configurable TTL, default 3600s).
- Existing API remains backward-compatible.

#### [NEW] `src/Middleware/SecurityHeaders.php`
Middleware that sets all hardened HTTP headers on every response:
- `Content-Security-Policy` (strict, configurable)
- `Strict-Transport-Security` (HSTS with preload)
- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy`
- Remove `X-Powered-By`

#### [MODIFY] [init.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/init.php)
- Load `.env` via Dotenv instead of `config.php`.
- Initialize structured logger (Monolog).
- Add session idle timeout enforcement.
- Add `session_regenerate_id(true)` trigger tracking.
- Call `SecurityHeaders` middleware.
- Remove hardcoded `date_default_timezone_set` — read from `APP_TIMEZONE` env.

---

### Phase 3: Database & Repository Layer

#### [MODIFY] [Database.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Database.php)
- Convert to **thread-safe Singleton** pattern: `Database::getInstance(): PDO`.
- Add connection pooling awareness (persistent connections option).
- Add `PDO::MYSQL_ATTR_FOUND_ROWS` for accurate `UPDATE` row counts.
- Set `PDO::ATTR_STRINGIFY_FETCHES = false` for proper type mapping.

#### [MODIFY] [AbstractRepository.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Abstracts/AbstractRepository.php)
- Use Singleton `Database::getInstance()` instead of `new Database()` per repo.
- Add **transaction helpers**: `beginTransaction()`, `commit()`, `rollBack()`, `transaction(callable $fn)`.
- Add **Generator-based `getAllStream()`** using `yield` for memory-efficient large result sets.
- Add **ownership verification** method: `isOwnedBy(int $id, int $userId): bool` — for IDOR prevention.
- Quote table name with backticks in all queries.
- Replace `count()` method's raw `query()` with prepared statement.

---

### Phase 4: Authorization (RBAC/ABAC)

#### [NEW] `src/Services/Authorization.php`
RBAC/ABAC middleware:
- Role definitions: `admin`, `editor`, `user`, `guest` (configurable).
- Permission map: role → `['users.read', 'users.write', 'posts.delete', ...]`.
- `Authorization::can(string $permission): bool` — checks current session user's role.
- `Authorization::owns(string $resource, int $resourceId): bool` — IDOR guard.

#### [MODIFY] [Route.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Route.php)
- Add `roles: array` parameter: `#[Route('/admin', roles: ['admin'])]`
- Add `permissions: array` parameter: `#[Route('/users', permissions: ['users.read'])]`

#### [MODIFY] [router.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/router.php)
- **Fix duplicate routing block** (lines 67-107 are copy of 26-65).
- Integrate RBAC check from Route attribute `roles`/`permissions`.
- Cache route table on first request (array-based route cache).

---

### Phase 5: Cloudflare Turnstile Integration

#### [NEW] `src/Services/Turnstile.php`
- `verify(string $responseToken, ?string $remoteIp = null): TurnstileResult`
- Server-side cURL call to `https://challenges.cloudflare.com/turnstile/v0/siteverify`
- Validates `success`, `hostname`, `challenge_ts` (timestamp freshness).
- Returns structured result object with error codes.

#### [NEW] `src/Views/includes/turnstile.php`
Reusable view partial: renders the Turnstile widget `<div class="cf-turnstile" data-sitekey="...">`.

---

### Phase 6: Rate Limiting & Threat Blocking

#### [NEW] `src/Middleware/RateLimiter.php`
- Sliding window algorithm (Redis-backed with file fallback).
- Configurable per-route limits: e.g., login → 5 req/min, API → 60 req/min.
- Returns `429 Too Many Requests` with `Retry-After` header.
- Logs severe violations (brute-force patterns) in Fail2Ban-parseable format.

#### [NEW] `src/Services/ThreatLogger.php`
- Writes security events (failed logins, CSRF violations, rate limit hits) to dedicated `logs/security.log`.
- Format compatible with Fail2Ban filter rules.
- PII masking: email addresses partially redacted, IPs kept for blocking.

---

### Phase 7: File Upload Security

#### [NEW] `src/Services/FileUpload.php`
- MIME verification via `finfo_file()` magic bytes.
- Extension whitelist: `jpg`, `jpeg`, `png`, `webp`, `gif`, `mp4`, `webm`, `pdf`.
- UUID rename: all files stored as `{uuid}.{ext}`.
- Storage path: `/storage/uploads/` (outside web root).
- Max file size enforcement (configurable, default 10MB).
- Max image dimensions check (anti-compression-bomb).

#### [NEW] `src/Services/ImageProcessor.php`
- Re-encode images via GD to strip EXIF metadata, embedded PHP tags, GPS coords.
- SVG sanitization: DOMDocument-based parser that strips `<script>`, `onload`, `xlink:href`.
- Thumbnail generation with configurable dimensions.

#### [NEW] `src/Controllers/FileController.php`
- `GET /file/{id}` — serves files from storage with proper `Content-Type`, `Content-Disposition`, `X-Content-Type-Options: nosniff` headers.
- Authorization check: only file owner or admin can access.

---

### Phase 8: Performance & Caching

#### [NEW] `src/Services/Cache.php`
Multi-tier caching wrapper:
- **L1**: In-memory array (per-request).
- **L2**: Redis/Memcached (cross-request, if available).
- **Fallback**: File-based cache in `/storage/cache/`.
- API: `get(key)`, `set(key, value, ttl)`, `delete(key)`, `remember(key, ttl, closure)`.

#### [NEW] `src/Services/ResponseCompressor.php`
- Content negotiation for `Accept-Encoding: gzip, br`.
- `ob_start()` with `ob_gzhandler` or Brotli if extension available.
- Sets `ETag` and `Cache-Control` headers.

#### [MODIFY] [init.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/init.php)
- Call `session_write_close()` after session data loaded for read-only routes.

#### [NEW] `opcache.ini` (recommended config)
```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  ; set to 1 in dev
opcache.revalidate_freq=0
opcache.save_comments=1
```

---

### Phase 9: API & Validation

#### [NEW] `src/Services/ApiResponse.php`
Normalized JSON responses:
```php
ApiResponse::success($data, $meta, 200);
// {"status":"success","data":{...},"errors":null,"meta":{...}}
ApiResponse::error($errors, 422);
// {"status":"error","data":null,"errors":[...],"meta":null}
```

#### [MODIFY] [Validator.php](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/src/Services/Validator.php)
- Add rules: `regex:pattern`, `confirmed` (password confirm), `in:val1,val2`, `url`, `date`, `boolean`, `file`, `image`, `max_file_size:bytes`.
- Add `alpha`, `alpha_num`, `slug` rules.
- Custom error messages support: `['email.required' => 'Email is mandatory']`.

#### [NEW] `src/Middleware/Cors.php`
Fine-grained CORS middleware:
- Configurable allowed origins, methods, headers.
- Handles preflight `OPTIONS` requests.
- `Access-Control-Allow-Credentials` support.

---

### Phase 10: Logging & Observability

#### [NEW] `src/Services/Logger.php`
PSR-3 compatible wrapper around Monolog:
- Channels: `app`, `security`, `database`, `mail`.
- Structured JSON output to `logs/{channel}-{date}.log`.
- PII auto-masking filter (emails, phone numbers).
- Context enrichment: request ID, user ID, IP.

#### [NEW] `src/Middleware/RequestLogger.php`
Logs every incoming request: method, path, status code, duration, memory peak. Excludes static assets.

---

### Phase 11: Queue System & Resilience

#### [NEW] `src/Services/JobQueue.php`
Lightweight job queue:
- SQL-backed by default (`jobs` table with `payload`, `status`, `attempts`, `run_at`).
- Redis-backed option if available.
- Worker script: `php bin/worker.php` — processes pending jobs.
- Built-in retry with exponential backoff.

#### [NEW] `src/Services/CircuitBreaker.php`
- Wraps external API calls (mailer, payment, webhooks).
- States: `CLOSED` → `OPEN` → `HALF_OPEN`.
- Configurable failure threshold, timeout, retry window.

#### [NEW] Health Endpoints
- `GET /health` — returns `200 {"status":"healthy","checks":{...}}` if DB + Redis OK.
- `GET /ready` — returns `200` only if all services are available for traffic.

---

### Phase 12: Database Migrations

#### [NEW] `src/Services/Migrator.php`
Lightweight migration engine:
- Reads timestamped PHP files from `src/Migrations/`.
- Tracks applied migrations in `_migrations` table.
- CLI: `php bin/migrate.php up`, `php bin/migrate.php down`, `php bin/migrate.php status`.

#### [NEW] Migration files
```
src/Migrations/
├── 2024_001_create_users_table.php      # up() / down()
├── 2024_002_create_roles_table.php
├── 2024_003_create_permissions_table.php
├── 2024_004_create_sessions_table.php
├── 2024_005_create_jobs_table.php
├── 2024_006_create_rate_limits_table.php
└── 2024_007_create_migrations_table.php
```

---

### Phase 13: DevOps & Docker

#### [NEW] `Dockerfile`
Optimized multi-stage build:
- Base: `php:8.2-fpm-alpine`
- Extensions: `pdo_mysql`, `opcache`, `gd`, `redis`, `curl`, `openssl`, `mbstring`
- Non-root user, read-only filesystem where possible.

#### [NEW] `docker-compose.yml`
Full stack:
- `app` (PHP-FPM)
- `web` (Nginx with optimized config)
- `db` (MySQL 8.0 / PostgreSQL 16)
- `redis` (Redis 7-alpine)
- `mailpit` (dev mail catcher)

#### [NEW] `docker/nginx/default.conf`
Production-ready Nginx config:
- PHP-FPM upstream
- Deny access to dotfiles, vendor/, src/
- Force `/public/` as document root
- Block script execution in storage/

#### [NEW] `phpunit.xml`
Test configuration targeting `tests/` directory.

#### [NEW] `phpstan.neon`
Level 6 static analysis config.

---

### Phase 14: Testing

#### [NEW] Test Suite
```
tests/
├── Unit/
│   ├── CsrfTest.php              # Token generation, validation, expiry
│   ├── PasswordHasherTest.php     # Argon2id hashing, verification, rehash
│   ├── EncryptionTest.php         # AES-256-GCM encrypt/decrypt roundtrip
│   ├── ValidatorTest.php          # All validation rules
│   ├── RateLimiterTest.php        # Sliding window, limits
│   └── TurnstileTest.php          # Mock server-side verification
├── Integration/
│   └── AuthFlowTest.php           # Login, session, logout
└── bootstrap.php
```

---

### Phase 15: Documentation

#### [MODIFY] [readme.md](file:///e:/personal_project/template_empty_mvc_for_any_new_project_php_native/readme.md) — Complete rewrite
Full production documentation:
1. Architecture diagram & directory layout
2. Request lifecycle flowchart
3. Installation (prerequisites, `.env` setup, migrations, server config)
4. Security features & configuration
5. API conventions
6. Cloudflare Turnstile setup
7. Docker deployment
8. Testing & CI/CD
9. Maintenance checklist

#### [NEW] `SECURITY.md`
Security operations runbook: key rotation, dependency auditing, log monitoring, incident response.

---

## Final Directory Structure

```
project-root/
├── bin/
│   ├── migrate.php
│   └── worker.php
├── docker/
│   └── nginx/
│       └── default.conf
├── logs/                        # gitignored
├── public/                      # web root
│   ├── .htaccess
│   ├── index.php
│   ├── assets/
│   └── robots.txt
├── src/
│   ├── Abstracts/
│   │   ├── AbstractController.php
│   │   └── AbstractRepository.php
│   ├── Controllers/
│   │   ├── FileController.php   [NEW]
│   │   ├── HealthController.php [NEW]
│   │   ├── HomeController.php
│   │   └── UserController.php
│   ├── Entities/
│   │   └── User.php
│   ├── Middleware/               [NEW]
│   │   ├── Cors.php
│   │   ├── RateLimiter.php
│   │   ├── RequestLogger.php
│   │   └── SecurityHeaders.php
│   ├── Migrations/
│   │   ├── 2024_001_create_users_table.php
│   │   └── ...
│   ├── Repositories/
│   │   └── UserRepository.php
│   ├── Services/
│   │   ├── ApiResponse.php      [NEW]
│   │   ├── Authorization.php    [NEW]
│   │   ├── Cache.php            [NEW]
│   │   ├── CircuitBreaker.php   [NEW]
│   │   ├── Config.php           [NEW]
│   │   ├── ConfigRouter.php
│   │   ├── Csrf.php
│   │   ├── Database.php
│   │   ├── Encryption.php       [RENAMED]
│   │   ├── FileUpload.php       [NEW]
│   │   ├── Hydration.php
│   │   ├── ImageProcessor.php   [NEW]
│   │   ├── JobQueue.php         [NEW]
│   │   ├── Logger.php           [NEW]
│   │   ├── Mail.php
│   │   ├── Migrator.php         [NEW]
│   │   ├── PasswordHasher.php   [NEW]
│   │   ├── ResponseCompressor.php [NEW]
│   │   ├── Route.php
│   │   ├── router.php
│   │   ├── ThreatLogger.php     [NEW]
│   │   ├── Turnstile.php        [NEW]
│   │   └── Validator.php
│   └── Views/
│       ├── dashboard/
│       ├── home/
│       └── includes/
│           └── turnstile.php    [NEW]
├── storage/                     [NEW] gitignored
│   ├── cache/
│   └── uploads/
├── tests/                       [NEW]
├── .env.example                 [NEW]
├── .gitignore
├── composer.json
├── docker-compose.yml           [NEW]
├── Dockerfile                   [NEW]
├── opcache.ini                  [NEW]
├── phpstan.neon                 [NEW]
├── phpunit.xml                  [NEW]
├── readme.md
├── readme.fr.md
└── SECURITY.md                  [NEW]
```

---

## Verification Plan

### Automated Tests
```bash
# Run full test suite
composer test

# Static analysis
vendor/bin/phpstan analyse src/ --level=6

# Security audit
composer audit
```

### Manual Verification
- Verify CSRF token validation rejects forged/expired tokens
- Verify AES-256-GCM encrypt→decrypt roundtrip
- Verify Argon2id hashing and verification
- Verify rate limiter blocks after threshold
- Verify security headers present in all HTTP responses
- Verify `.env` is inaccessible from browser
- Verify uploaded files stored outside web root
- Verify `/health` endpoint returns service status
- Verify Docker stack starts and serves requests
- Verify database migrations run up/down cleanly

---

## Execution Order

| Phase | Component | Est. Files | Priority |
|-------|-----------|------------|----------|
| 1 | Foundation & Config | 5 | 🔴 Critical |
| 2 | Security Core | 6 | 🔴 Critical |
| 3 | Database Layer | 2 | 🔴 Critical |
| 4 | RBAC/ABAC | 3 | 🟡 High |
| 5 | Turnstile | 2 | 🟡 High |
| 6 | Rate Limiting | 2 | 🟡 High |
| 7 | File Upload | 3 | 🟢 Medium |
| 8 | Performance | 4 | 🟢 Medium |
| 9 | API & Validation | 3 | 🟢 Medium |
| 10 | Logging | 2 | 🟢 Medium |
| 11 | Queue & Resilience | 3 | 🟢 Medium |
| 12 | Migrations | 8 | 🟡 High |
| 13 | DevOps & Docker | 4 | 🟢 Medium |
| 14 | Testing | 7 | 🟡 High |
| 15 | Documentation | 3 | 🟡 High |

**Total: ~60 new/modified files**
