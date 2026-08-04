# Security

This document covers every security mechanism implemented in the framework. For operational procedures (key rotation, incident response), see [SECURITY.md](../SECURITY.md).

**Source files:** `src/Services/Encryption.php`, `src/Services/PasswordHasher.php`, `src/Services/Csrf.php`, `src/Middleware/SecurityHeaders.php`

---

## Encryption (AES-256-GCM)

### Overview

The `Encryption` service provides **authenticated encryption** using AES-256-GCM (AEAD). Unlike the old AES-256-CBC implementation, GCM provides both confidentiality and integrity verification in a single operation — any tampering is detected automatically.

### How It Works

1. A 12-byte random nonce (IV) is generated per encryption.
2. Data is encrypted with AES-256-GCM, producing ciphertext + a 16-byte authentication tag.
3. The payload is packed as: `IV (12 bytes) + Tag (16 bytes) + Ciphertext (variable)`.
4. The packed payload is encoded as URL-safe base64.

### Key Derivation

The raw `APP_KEY` from `.env` is not used directly. Instead, it is passed through **HKDF** (Hash-based Key Derivation Function) with SHA-256:

```
Master Key (from .env) → HKDF(sha256, 32 bytes, "aes-256-gcm-encryption") → Derived Key
```

This provides domain separation — using the same master key for different purposes produces different derived keys.

### API

```php
use App\Services\Encryption;

$enc = new Encryption();

// Encrypt
$cipher = $enc->encrypt('sensitive data');
// Output: URL-safe base64 string like "dGVzdA..." (varies each call due to random IV)

// Decrypt
$plain = $enc->decrypt($cipher);
// Output: "sensitive data" or false if tampered

// Encrypt/decrypt IDs for URLs
$token = $enc->encryptId(42);
$id    = $enc->decryptId($token); // "42" or false
```

### Security Properties

| Property | Guaranteed? |
|----------|-------------|
| Confidentiality | ✅ AES-256 encryption |
| Integrity | ✅ GCM authentication tag |
| Authenticity | ✅ Only holders of the key can create valid payloads |
| Nonce uniqueness | ✅ 12-byte random nonce per operation |
| Timing safety | ✅ OpenSSL handles tag comparison internally |

---

## Password Hashing (Argon2id)

### Overview

The `PasswordHasher` service uses **Argon2id** — the recommended algorithm by OWASP and NIST. It combines:
- **Argon2d** — resistance to GPU-based attacks (memory-hard)
- **Argon2i** — resistance to side-channel attacks (data-independent)

### Cost Parameters

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `memory_cost` | 65,536 KiB (64 MB) | Forces each hash to use 64 MB of RAM |
| `time_cost` | 4 iterations | Number of passes over memory |
| `threads` | 2 | Degree of parallelism |

Target: ~0.5–1 second per hash on production hardware.

### API

```php
use App\Services\PasswordHasher;

// Hash a password
$hash = PasswordHasher::hash('MyP@ssw0rd!');
// Output: "$argon2id$v=19$m=65536,t=4,p=2$..."

// Verify a password
$valid = PasswordHasher::verify('MyP@ssw0rd!', $hash);
// Output: true

// Check if hash needs upgrading (e.g., from bcrypt or older params)
$needsRehash = PasswordHasher::needsRehash($hash);
// Output: false (if current params match)
```

### Transparent Rehashing

After successful login, call `needsRehash()` on the stored hash. If it returns `true`, re-hash the password and update the database. This automatically upgrades:
- bcrypt → Argon2id
- Argon2id with old cost parameters → current parameters

---

## CSRF Protection

### Overview

The `Csrf` service implements the **Synchronizer Token Pattern** with two modes:

1. **Global session token** — simple, backward-compatible
2. **Per-form scoped tokens** — stronger, with expiry and one-time consumption

### Global Token Usage

**In forms:**
```php
<form method="POST" action="/submit">
    <?= \App\Services\Csrf::inputField() ?>
    <!-- form fields -->
</form>
```

**In AJAX:**
```javascript
fetch('/api/data', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': '<?= \App\Services\Csrf::getToken() ?>',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});
```

### Per-Form Scoped Tokens

For stronger protection, use form-scoped tokens that:
- Are unique per form
- Expire after 1 hour (configurable)
- Are consumed after use (one-time only)

```php
<form method="POST" action="/login">
    <?= \App\Services\Csrf::inputField('login') ?>
    <!-- form fields -->
</form>
```

### API

| Method | Description |
|--------|-------------|
| `Csrf::getToken(?string $formId = null)` | Get or generate a token |
| `Csrf::validateToken(string $token, ?string $formId = null)` | Validate a submitted token |
| `Csrf::refreshToken()` | Force-generate a new global token |
| `Csrf::inputField(?string $formId = null)` | Return HTML hidden input(s) |

### Token Properties

| Property | Global Token | Per-Form Token |
|----------|-------------|----------------|
| Length | 64 hex chars (32 bytes) | 64 hex chars (32 bytes) |
| Scope | Session-wide | Specific form |
| Expiry | Session lifetime | 1 hour (configurable) |
| Reusability | Reusable | One-time use |
| Comparison | `hash_equals()` | `hash_equals()` |

---

## Security Headers

### Overview

The `SecurityHeaders` middleware sends defense-in-depth HTTP response headers on every request.

### Headers Sent

| Header | Value | Attack Prevented |
|--------|-------|-----------------|
| `X-Content-Type-Options` | `nosniff` | MIME-type confusion XSS |
| `X-Frame-Options` | `SAMEORIGIN` | Clickjacking |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Referer data leakage |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=(), payment=(), usb=()` | Feature abuse |
| `Content-Security-Policy` | Strict allowlist | XSS, data injection, clickjacking |
| `Cross-Origin-Opener-Policy` | `same-origin` | Cross-origin window manipulation |
| `Cross-Origin-Resource-Policy` | `same-origin` | Cross-origin resource theft |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` *(production only)* | SSL stripping |

### Content Security Policy Details

```
default-src 'self';
script-src 'self';
style-src 'self' 'unsafe-inline';
img-src 'self' data: https:;
font-src 'self' https://fonts.gstatic.com;
connect-src 'self';
frame-src 'self' https://challenges.cloudflare.com;
child-src 'self' https://challenges.cloudflare.com;
frame-ancestors 'self';
base-uri 'self';
form-action 'self';
object-src 'none';
upgrade-insecure-requests;
```

### Customizing CSP

If your application loads external scripts (analytics, CDN libraries), modify the `$csp` array in `SecurityHeaders::send()`:

```php
// Example: allow Google Analytics
"script-src 'self' https://www.googletagmanager.com",
"connect-src 'self' https://www.google-analytics.com",
```

---

## Output Encoding

Always escape user-provided data before rendering in HTML:

```php
// In views
<?= htmlspecialchars($userInput, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>

// Using the Validator helper
<?= \App\Services\Validator::escape($userInput) ?>
```

The `Validator::escape()` method is equivalent to `htmlspecialchars()` with `ENT_QUOTES | ENT_SUBSTITUTE` and `UTF-8`.

---

## Session Hardening

Session security is configured in `init.php`:

| Setting | Value | Purpose |
|---------|-------|---------|
| `use_strict_mode` | `1` | Reject unknown session IDs |
| `use_only_cookies` | `1` | Never accept session ID in URLs |
| `cookie_httponly` | `true` | Block JavaScript access to session cookie |
| `cookie_samesite` | `Strict` | Prevent cross-site request cookie sending |
| `cookie_secure` | `true` *(production)* | HTTPS-only session cookies |
| Idle timeout | 30 min (configurable) | Destroy session after inactivity |
| Regeneration | Every 5 minutes | `session_regenerate_id(true)` |
| Login regeneration | On authentication | Prevents session fixation |
| Fingerprinting | IP + User-Agent | Detects stolen sessions |
