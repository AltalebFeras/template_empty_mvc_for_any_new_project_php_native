# Logging & Threat Monitoring

Covers structured PSR-3 logging with Monolog, automatic request timing, PII masking, and Fail2Ban security threat logging.

**Source files:** `src/Services/Logger.php`, `src/Middleware/RequestLogger.php`, `src/Middleware/ThreatLogger.php`

---

## Structured Logger (`Logger`)

The `Logger` service wraps [Monolog](https://github.com/Seldaek/monolog) and provides PSR-3 compliant, channel-based structured logging with JSON formatting.

### Log Channels

Log files are stored in `logs/{channel}-{date}.log`:

| Channel | File Name | Purpose |
|---------|-----------|---------|
| `app` | `logs/app-YYYY-MM-DD.log` | General application logs |
| `security` | `logs/security-YYYY-MM-DD.log` | Authentication, CSRF, RBAC events |
| `database` | `logs/database-YYYY-MM-DD.log` | Query failures and DB connection errors |
| `mail` | `logs/mail-YYYY-MM-DD.log` | Email sending errors and status |

### Usage

```php
use App\Services\Logger;

// Log to default 'app' channel
Logger::info('User updated profile', ['user_id' => 42]);
Logger::error('Payment gateway timeout', ['order_id' => 1001]);

// Log to specific channels
Logger::channel('security')->warning('Failed login attempt', ['email' => 'user@example.com']);
Logger::channel('database')->critical('DB connection failed', ['host' => '127.0.0.1']);
Logger::channel('mail')->error('SMTP delivery failed', ['to' => 'user@example.com']);
```

### Context Enrichment

Every log record is automatically enriched with contextual processor data:

- `request_id`: Unique HTTP request identifier (16-char hex)
- `ip`: Client IP address (`$_SERVER['REMOTE_ADDR']`)
- `user_id`: Authenticated user ID (if logged in)

### PII Masking

The log processor automatically redacts personally identifiable information (PII) before writing to disk:

- **Email addresses**: `user@example.com` → `u***@example.com`
- **Sensitive keys** (`password`, `token`, `secret`, `credit_card`, `ssn`): Replaced with `[REDACTED]`

---

## Request Logger (`RequestLogger`)

Logs timing and performance metrics for every HTTP request using a PHP shutdown handler.

### What Is Tracked

```json
{
    "message": "Request completed",
    "context": {
        "method": "POST",
        "path": "/login",
        "status": 200,
        "duration_ms": 45.12,
        "memory_mb": 4.25
    }
}
```

Static asset requests (`.css`, `.js`, `.jpg`, `.png`, etc.) are automatically excluded from logging.

---

## Threat Logger (`ThreatLogger`)

Writes security events to a dedicated log file (`logs/security-threats.log`) in a format parseable by **Fail2Ban** and WAF log analyzers.

### Log Format

```
[2024-01-15T14:30:00+00:00] THREAT type=brute_force ip=192.168.1.1 action=login attempts=10
[2024-01-15T14:31:05+00:00] THREAT type=csrf_violation ip=192.168.1.2 path=/admin/settings
```

### Usage

```php
use App\Middleware\ThreatLogger;

// Log generic security threat
ThreatLogger::log('rate_limit', $clientIp, ['action' => 'login', 'attempts' => 10]);

// Log brute force attempt
ThreatLogger::bruteForce($clientIp, 'login', 5);

// Log malicious payload detection
ThreatLogger::maliciousPayload($clientIp, '/api/users', "SQLi attempt: SELECT * FROM users");
```

### Fail2Ban Configuration

Example Fail2Ban filter (`/etc/fail2ban/filter.d/php-app.conf`):

```ini
[Definition]
failregex = THREAT type=\S+ ip=<HOST>
```

Example jail (`/etc/fail2ban/jail.local`):

```ini
[php-app]
enabled  = true
port     = http,https
filter   = php-app
logpath  = /var/www/logs/security-threats.log
maxretry = 5
findtime = 600
bantime  = 3600
```
