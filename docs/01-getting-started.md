# Getting Started

## Prerequisites

| Requirement | Minimum Version | Notes |
|-------------|----------------|-------|
| PHP | 8.2+ | Argon2id requires libargon2. Check with `php -m \| grep argon` |
| Composer | 2.0+ | [getcomposer.org](https://getcomposer.org/) |
| MySQL | 8.0+ | Or PostgreSQL 16+ (set `DB_DRIVER=pgsql`) |
| Redis | 7+ | **Optional** — file-based fallback used when unavailable |
| cURL | Any | Required for Cloudflare Turnstile verification |

### Required PHP Extensions

```
pdo_mysql     # Database (or pdo_pgsql for PostgreSQL)
openssl       # AES-256-GCM encryption
mbstring      # Multi-byte string support (validation, UTF-8)
curl          # Turnstile API calls
gd            # Image processing (re-encoding, thumbnails)
json          # JSON encoding/decoding
fileinfo      # MIME-type detection for uploads
session       # Session management
```

Verify extensions are installed:

```bash
php -m | grep -E 'pdo_mysql|openssl|mbstring|curl|gd|json|fileinfo|session'
```

### Optional PHP Extensions

```
redis         # Native PHP Redis extension (alternative to predis)
imagick       # Advanced image processing
apcu          # In-memory caching (alternative to Redis)
opcache       # Bytecode caching (strongly recommended in production)
```

---

## Installation

### Step 1: Clone the Repository

```bash
git clone <repository-url> myproject
cd myproject
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

This installs:
- `vlucas/phpdotenv` — Environment variable loader
- `phpmailer/phpmailer` — SMTP email client
- `monolog/monolog` — Structured logging (PSR-3)
- `predis/predis` — Redis client

Dev dependencies:
- `phpunit/phpunit` — Testing framework
- `phpstan/phpstan` — Static analysis

### Step 3: Configure the Environment

```bash
cp .env.example .env
```

Open `.env` and configure at minimum:

```env
# REQUIRED — generate with: php -r "echo bin2hex(random_bytes(32));"
APP_KEY=your_64_character_hex_string_here

# REQUIRED — database connection
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

# Adjust for your environment
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080
```

### Step 4: Generate Encryption Key

The `APP_KEY` must be exactly 64 hexadecimal characters (representing 32 bytes):

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Copy the output into your `.env` file as the `APP_KEY` value.

> ⚠️ **Never reuse keys across environments.** Generate a unique key for development, staging, and production.

### Step 5: Create the Database

```sql
CREATE DATABASE your_database_name
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

### Step 6: Run Migrations

```bash
php bin/migrate.php up
```

This creates the `users`, `roles`, `permissions`, `role_permissions`, and `jobs` tables.

Check migration status:

```bash
php bin/migrate.php status
```

### Step 7: Set Directory Permissions

```bash
# Linux/macOS
chmod -R 755 storage/ logs/
chown -R www-data:www-data storage/ logs/

# Verify
ls -la storage/ logs/
```

### Step 8: Start the Application

#### Option A: PHP Built-In Server (Development)

```bash
php -S localhost:8080 -t public/
```

Open [http://localhost:8080](http://localhost:8080) in your browser.

#### Option B: Docker (Recommended)

```bash
docker-compose up -d
```

Services started:
- **App**: http://localhost:8080 (Nginx → PHP-FPM)
- **Mailpit**: http://localhost:8025 (email catcher UI)
- **MySQL**: localhost:3306
- **Redis**: localhost:6379

#### Option C: Apache/Nginx (Production)

Point your virtual host's document root to the `public/` directory. See [Docker & Deployment](18-docker-deployment.md) for server configuration.

---

## Verifying the Installation

### 1. Homepage

Navigate to `http://localhost:8080/`. You should see the home page.

### 2. Health Check

```bash
curl http://localhost:8080/health
```

Expected response:
```json
{
    "status": "success",
    "data": {
        "status": "healthy",
        "checks": {
            "database": true,
            "redis": true
        },
        "timestamp": "2024-01-15T14:30:00+00:00"
    }
}
```

### 3. Run Tests

```bash
composer test
```

Expected: `OK (42 tests, 82 assertions)`

### 4. Static Analysis

```bash
composer analyse
```

---

## Project Structure Overview

```
myproject/
├── bin/           CLI scripts (migrate, worker)
├── docker/        Docker configuration files
├── docs/          Documentation (you are here)
├── logs/          Application log files (auto-created)
├── public/        Web root (document root for your server)
├── src/           Application source code (App\ namespace)
├── storage/       Uploads and cache (auto-created)
├── tests/         PHPUnit test suite
├── .env           Environment configuration (never commit!)
├── .env.example   Environment template
├── composer.json  PHP dependencies and autoloading
└── readme.md      Project overview
```

See [Architecture](02-architecture.md) for the full directory tree and design patterns.

---

## Next Steps

1. **Understand the architecture** → [Architecture](02-architecture.md)
2. **Configure all settings** → [Configuration](03-configuration.md)
3. **Build your first feature** → [Controllers](05-controllers.md) + [Database](09-database.md)
4. **Secure your forms** → [Security](06-security.md)
5. **Deploy to production** → [Docker & Deployment](18-docker-deployment.md)
