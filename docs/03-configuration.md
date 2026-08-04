# Configuration

All application configuration is managed through environment variables loaded from a `.env` file using [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv).

**Source file:** `src/Services/Config.php`

---

## How It Works

1. On application boot, `Config::boot()` is called in `src/init.php`.
2. The `Config` singleton loads `.env` from the project root via `Dotenv::createImmutable()`.
3. Required keys are validated — if any are missing, the application throws a `RuntimeException` and refuses to start.
4. All values are cached in an internal array for fast lookup.

---

## Required Environment Keys

These keys **must** be present and non-empty in `.env`:

| Key | Description |
|-----|-------------|
| `APP_ENV` | Environment name: `development`, `production`, or `testing` |
| `APP_KEY` | 64 hex chars (32 bytes) — encryption master key |
| `APP_URL` | Base URL (e.g., `http://localhost:8080` or `https://example.com`) |
| `DB_HOST` | Database server hostname or IP |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |

---

## All Environment Variables

### Application

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `APP_ENV` | string | `development` | Environment mode. Controls error display, HSTS, secure cookies |
| `APP_DEBUG` | bool | `true` | Show stack traces on error. **Must be `false` in production** |
| `APP_URL` | string | `http://localhost` | Base URL (no trailing slash) |
| `APP_TIMEZONE` | string | `Europe/Paris` | PHP default timezone |
| `APP_KEY` | string | *(required)* | Encryption key — exactly 64 hex characters |

### Database

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `DB_DRIVER` | string | `mysql` | Database driver: `mysql` or `pgsql` |
| `DB_HOST` | string | `127.0.0.1` | Database server host |
| `DB_PORT` | int | `3306` | Database server port |
| `DB_NAME` | string | *(required)* | Database name |
| `DB_USER` | string | *(required)* | Database username |
| `DB_PASS` | string | *(empty)* | Database password |
| `DB_CHARSET` | string | `utf8mb4` | Connection character set |

### Session

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `SESSION_LIFETIME` | int | `7200` | Maximum session lifetime in seconds (2 hours) |
| `SESSION_IDLE_TIMEOUT` | int | `1800` | Auto-destroy session after idle seconds (30 minutes) |

### Mail (SMTP)

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `MAIL_HOST` | string | *(empty)* | SMTP server hostname |
| `MAIL_PORT` | int | `587` | SMTP port (587 for TLS, 465 for SSL) |
| `MAIL_USERNAME` | string | *(empty)* | SMTP authentication username |
| `MAIL_PASSWORD` | string | *(empty)* | SMTP authentication password |
| `MAIL_ENCRYPTION` | string | `tls` | Encryption: `tls`, `ssl`, or empty for none |
| `MAIL_FROM_ADDRESS` | string | `noreply@example.com` | Default sender email |
| `MAIL_FROM_NAME` | string | `App` | Default sender display name |
| `MAIL_ADMIN_ADDRESS` | string | *(empty)* | Admin notification email address |

### Redis

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `REDIS_HOST` | string | `127.0.0.1` | Redis server host |
| `REDIS_PORT` | int | `6379` | Redis server port |
| `REDIS_PASSWORD` | string | *(empty)* | Redis authentication password |

### Cloudflare Turnstile

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `TURNSTILE_SITE_KEY` | string | *(empty)* | Public site key (shown in widget) |
| `TURNSTILE_SECRET_KEY` | string | *(empty)* | Secret key (server-side verification) |

### Rate Limiting

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `RATE_LIMIT_LOGIN` | int | `5` | Max login attempts per window |
| `RATE_LIMIT_API` | int | `60` | Max API requests per window |
| `RATE_LIMIT_WINDOW` | int | `60` | Sliding window duration in seconds |

### File Uploads

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `UPLOAD_MAX_SIZE` | int | `10485760` | Maximum file size in bytes (10 MB) |
| `UPLOAD_ALLOWED_EXTENSIONS` | string | `jpg,jpeg,png,...` | Comma-separated allowed extensions |
| `STORAGE_PATH` | string | `storage` | Upload storage directory (relative to root) |

### Logging

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `LOG_CHANNEL` | string | `app` | Default log channel |
| `LOG_LEVEL` | string | `debug` | Minimum log level: debug, info, notice, warning, error, critical |
| `LOG_PATH` | string | `logs` | Log directory (relative to root) |

---

## Config Service API

### `Config::boot(): void`

Initializes the configuration. Called automatically in `init.php`. Idempotent — safe to call multiple times.

### `Config::get(string $key, ?string $default = null): ?string`

Returns a configuration value as a string.

```php
$host = Config::get('DB_HOST', '127.0.0.1');
$name = Config::get('APP_NAME'); // null if not set
```

### `Config::getBool(string $key, bool $default = false): bool`

Returns a configuration value as a boolean. Treats `'true'`, `'1'`, `'yes'`, `'on'` (case-insensitive) as `true`.

```php
$debug = Config::getBool('APP_DEBUG', false);
```

### `Config::getInt(string $key, int $default = 0): int`

Returns a configuration value as an integer.

```php
$port = Config::getInt('DB_PORT', 3306);
$maxSize = Config::getInt('UPLOAD_MAX_SIZE', 10485760);
```

### `Config::isProduction(): bool`

Returns `true` if `APP_ENV` equals `'production'`.

```php
if (Config::isProduction()) {
    // Enable HSTS, disable debug, etc.
}
```

### `Config::isDebug(): bool`

Returns `true` if `APP_DEBUG` is truthy.

### `Config::getEncryptionKey(): string`

Returns the binary encryption key (32 raw bytes) decoded from the hex `APP_KEY`.

Throws `RuntimeException` if:
- `APP_KEY` is empty
- `APP_KEY` is not exactly 64 hex characters
- `APP_KEY` contains invalid hex characters

### `Config::baseUrl(): string`

Returns the application base URL with no trailing slash.

```php
$url = Config::baseUrl(); // "https://example.com"
```

---

## Environment-Specific Behavior

| Feature | Development | Production |
|---------|-------------|------------|
| Error display | Stack trace in browser | Clean 500 error page |
| Error logging | `display_errors = 1` | `log_errors = 1` to file |
| HSTS header | Not sent | `max-age=31536000; includeSubDomains; preload` |
| Session cookies | `Secure = false` | `Secure = true` (HTTPS only) |
| Turnstile | Bypassed if no key set | Required in production |
| SMTP encryption | Disabled for local relay | TLS/SSL enforced |

---

## Adding Custom Configuration

To add a new configuration key:

1. Add the key to `.env.example` with a comment and default value.
2. Access it via `Config::get('YOUR_KEY', 'default')`.
3. If it's required, add it to `Config::REQUIRED_KEYS` in `Config.php`.
