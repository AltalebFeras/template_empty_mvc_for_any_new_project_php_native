# Security Operations Runbook

## Reporting Vulnerabilities

If you discover a security vulnerability, please report it responsibly. **Do not open a public issue.**

Send a detailed report to your project's security contact email.

---

## Encryption Key Management

### Initial Key Generation

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Set the output as `APP_KEY` in your `.env` file. This key is used for AES-256-GCM encryption of sensitive data.

### Key Rotation

1. Generate a new key using the command above.
2. Decrypt all existing encrypted data using the **old** key.
3. Re-encrypt all data using the **new** key.
4. Update `APP_KEY` in `.env`.
5. Restart all PHP-FPM workers to pick up the new key.

> ⚠️ **Never rotate the key without re-encrypting existing data** — all previously encrypted payloads will become undecryptable.

---

## Password Hashing

The application uses **Argon2id** with the following parameters:

| Parameter | Value |
|-----------|-------|
| Algorithm | Argon2id |
| Memory Cost | 64 MB (65536 KiB) |
| Time Cost | 4 iterations |
| Threads | 2 |

These parameters are configured in `src/Services/PasswordHasher.php`. Tune them to achieve ~0.5–1 second per hash on your production hardware.

### Transparent Rehashing

The `UserController::processLogin()` method automatically rehashes passwords using `PasswordHasher::needsRehash()` after a successful login. This means:
- Upgrading from bcrypt to Argon2id is seamless.
- Changing cost parameters takes effect gradually as users log in.

---

## Session Security Checklist

- [x] `session.use_strict_mode = 1` — rejects unknown session IDs.
- [x] `session.use_only_cookies = 1` — never accepts session ID in URLs.
- [x] `cookie_httponly = true` — blocks JavaScript access to session cookie.
- [x] `cookie_samesite = Strict` — prevents cross-site request cookie sending.
- [x] `cookie_secure = true` in production — HTTPS-only cookies.
- [x] Session regeneration every 5 minutes (`session_regenerate_id(true)`).
- [x] Idle timeout (configurable, default 30 minutes).
- [x] IP + User-Agent fingerprint binding.
- [x] Full session regeneration on login.

---

## Dependency Auditing

Run regularly (at minimum before every deployment):

```bash
# Check for known vulnerabilities in PHP dependencies
composer audit

# Update dependencies to latest secure versions
composer update --prefer-stable

# Run static analysis
composer analyse
```

### Recommended Schedule

| Action | Frequency |
|--------|-----------|
| `composer audit` | Weekly or on every CI build |
| `composer update` | Monthly |
| PHP version upgrade check | Quarterly |
| Encryption key audit | Bi-annually |
| Full security review | Annually |

---

## HTTP Security Headers

All headers are set by `src/Middleware/SecurityHeaders.php`:

| Header | Value | Purpose |
|--------|-------|---------|
| Content-Security-Policy | Strict allow-list | Prevents XSS, data injection |
| Strict-Transport-Security | max-age=31536000 | Forces HTTPS for 1 year |
| X-Content-Type-Options | nosniff | Prevents MIME-type sniffing |
| X-Frame-Options | SAMEORIGIN | Prevents clickjacking |
| Referrer-Policy | strict-origin-when-cross-origin | Controls Referer leakage |
| Permissions-Policy | geolocation=(), camera=()... | Disables unused browser APIs |
| Cross-Origin-Opener-Policy | same-origin | Isolates browsing context |

---

## Rate Limiting

The rate limiter uses a **sliding window algorithm**:

| Endpoint | Limit | Window |
|----------|-------|--------|
| `/login` | 5 attempts | 60 seconds |
| API routes | 60 requests | 60 seconds |

Configure via `.env`:
```
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=60
RATE_LIMIT_WINDOW=60
```

### Fail2Ban Integration

Security events are logged to `logs/security-threats.log` in a format parseable by Fail2Ban:

```
[2024-01-15T14:30:00+00:00] THREAT type=brute_force ip=192.168.1.1 action=login attempts=10
```

Example Fail2Ban filter (`/etc/fail2ban/filter.d/php-app.conf`):
```ini
[Definition]
failregex = THREAT type=\S+ ip=<HOST>
```

---

## File Upload Security

All uploaded files pass through these validation steps:

1. **MIME verification** via `finfo_file()` magic bytes (not user-supplied Content-Type).
2. **Extension whitelist**: `jpg`, `jpeg`, `png`, `webp`, `gif`, `mp4`, `webm`, `pdf`.
3. **UUID rename**: all files stored as `{random_hex}.{ext}` — prevents overwrite and execution attacks.
4. **Storage outside web root**: files stored in `/storage/uploads/`, not `/public/`.
5. **Image re-encoding**: strips EXIF metadata, embedded PHP, GPS coordinates via GD.
6. **SVG sanitization**: DOMDocument-based stripping of `<script>`, event handlers, `xlink:href`.
7. **Dimension & memory limits**: prevents compression bombs.

---

## Cloudflare Turnstile

Turnstile protects public forms from automated abuse. Server-side verification:

1. Client submits `cf-turnstile-response` with the form.
2. Server validates token against `https://challenges.cloudflare.com/turnstile/v0/siteverify`.
3. Server checks `success`, `hostname` (must match `APP_URL`), and `challenge_ts` (max 5 min age).

### Setup

1. Register at [Cloudflare Dashboard](https://dash.cloudflare.com/turnstile).
2. Get Site Key and Secret Key.
3. Set `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` in `.env`.
4. Include `<?php include __DIR__ . '/../includes/turnstile.php'; ?>` in forms.

---

## Incident Response

### Suspected Breach

1. **Rotate `APP_KEY`** immediately (after re-encrypting data).
2. **Invalidate all sessions**: truncate the sessions table or clear session storage.
3. **Rotate database credentials** in `.env`.
4. **Review `logs/security-threats.log`** for attack patterns.
5. **Enable Cloudflare WAF** rules to block malicious IPs.
6. **Force password reset** for affected accounts.
7. **Notify affected users** per GDPR Article 34 requirements.

### Log Review

```bash
# Recent security events
tail -100 logs/security-threats.log

# Failed login attempts
grep "Failed login" logs/security-*.log

# Rate limit violations
grep "Rate limit exceeded" logs/security-*.log

# Application errors
tail -50 logs/app-$(date +%Y-%m-%d).log
```
