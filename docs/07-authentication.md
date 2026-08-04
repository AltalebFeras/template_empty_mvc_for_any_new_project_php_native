# Authentication

This document details the authentication flow, session management, and how to extend the login system.

**Source file:** `src/Controllers/UserController.php`

---

## Authentication Flow

```
User submits login form
    │
    ▼
┌──────────────────────────┐
│ 1. Rate Limit Check      │  Max 5 attempts / 60 sec
│    └── 429 if exceeded   │  (configurable via .env)
├──────────────────────────┤
│ 2. Turnstile Verify      │  Cloudflare bot detection
│    └── Redirect if fail  │  (bypassed in dev if no key)
├──────────────────────────┤
│ 3. Input Validation      │  email: required, valid format
│    └── Redirect + errors │  password: required, 1-128 chars
├──────────────────────────┤
│ 4. User Lookup           │  UserRepository::findByEmail()
│    └── "Invalid creds"   │  Generic message (no user enumeration)
├──────────────────────────┤
│ 5. Password Verify       │  PasswordHasher::verify()
│    └── Log failed attempt│  Logger::channel('security')
├──────────────────────────┤
│ 6. Rehash Check          │  PasswordHasher::needsRehash()
│    └── Update DB if yes  │  Transparent bcrypt→Argon2id upgrade
├──────────────────────────┤
│ 7. Session Hardening     │
│    ├── session_regenerate_id(true)
│    ├── Csrf::refreshToken()
│    ├── Store user data
│    ├── Store IP fingerprint
│    └── Store UA fingerprint
├──────────────────────────┤
│ 8. Redirect to /dashboard│
└──────────────────────────┘
```

---

## Session Variables

After successful login, the following session keys are set:

| Key | Type | Description |
|-----|------|-------------|
| `connected` | `bool` | `true` — used by auth guards |
| `user_id` | `int` | The authenticated user's primary key |
| `firstName` | `string` | Display name (for UI greetings) |
| `role` | `string` | User role: `'user'`, `'editor'`, or `'admin'` |
| `ip_address` | `string` | Client IP at login time (fingerprint) |
| `user_agent` | `string` | Client User-Agent at login time (fingerprint) |
| `_csrf_token` | `string` | Current CSRF token |
| `_last_activity` | `int` | Unix timestamp of last request (idle timeout) |
| `_created_at` | `int` | Session creation time (regeneration tracking) |

---

## Logout Process

```php
#[Route('/logout', methods: ['POST'])]
public function logout(): never
```

1. Log user ID for audit trail.
2. `session_unset()` — clear all session data.
3. `session_destroy()` — destroy the session on the server.
4. Start a new session for flash message.
5. Set `$_SESSION['success']` with logout confirmation.
6. Redirect to `/login`.

**Important:** Logout uses `POST`, not `GET`, to prevent CSRF-based forced logout via image tags or link prefetching.

---

## Session Security Mechanisms

### Idle Timeout

If a user hasn't made a request within `SESSION_IDLE_TIMEOUT` seconds (default: 1800 = 30 min):

1. Session is destroyed.
2. New session started with error message: "Your session expired due to inactivity."

### Periodic Regeneration

Every 5 minutes, `session_regenerate_id(true)` is called to:
- Generate a new session ID
- Delete the old session file
- Prevent session fixation attacks

### Fingerprint Validation

On every request, `ConfigRouter::checkOriginConnection()` compares:
- `$_SESSION['ip_address']` vs current `$_SERVER['REMOTE_ADDR']`
- `$_SESSION['user_agent']` vs current `$_SERVER['HTTP_USER_AGENT']`

If either changes, the session is considered hijacked — it's destroyed immediately and the user must re-authenticate.

---

## Extending Authentication

### Adding Registration

```php
#[Route('/register', methods: ['POST'])]
public function register(): void
{
    // 1. Rate limit
    RateLimiter::enforce('register', 3, 300);

    // 2. Turnstile
    $turnstile = Turnstile::verify($_POST['cf-turnstile-response'] ?? '');
    if ($turnstile->failed()) {
        $this->redirect('register', [], ['Bot verification failed.']);
    }

    // 3. Validate
    $errors = Validator::validate($_POST, [
        'first_name' => ['required', 'alpha', 'min:2', 'max:100'],
        'last_name'  => ['required', 'alpha', 'min:2', 'max:100'],
        'email'      => ['required', 'email', 'max:255'],
        'password'   => ['required', 'min:8', 'max:128', 'confirmed'],
    ]);

    if (!empty($errors)) {
        $this->redirect('register', [], $errors);
    }

    // 4. Check duplicate email
    $repo = new UserRepository();
    if ($repo->findByEmail($_POST['email'])) {
        $this->redirect('register', [], ['Email already registered.']);
    }

    // 5. Create user
    $repo->create([
        'first_name' => $_POST['first_name'],
        'last_name'  => $_POST['last_name'],
        'email'      => $_POST['email'],
        'password'   => PasswordHasher::hash($_POST['password']),
        'role_id'    => 1, // Default: 'user' role
    ]);

    $_SESSION['success'] = 'Registration successful. Please log in.';
    $this->redirect('login');
}
```

### Adding "Remember Me"

1. Generate a secure random token.
2. Store a hash of it in a `remember_tokens` database table (user_id, token_hash, expires_at).
3. Set a long-lived, HttpOnly, Secure cookie with the raw token.
4. On page load, if no session but a remember cookie exists: look up the token hash, verify, create session.
5. Rotate the token on every use (prevent replay).

### Adding Password Reset

1. User submits email → generate a signed, time-limited token (e.g., using `Encryption::encrypt()`).
2. Send email with reset link: `/reset-password?token=...`.
3. On the reset page, decrypt the token, verify it hasn't expired.
4. Accept new password, hash with `PasswordHasher::hash()`, update database.
5. Invalidate all existing sessions for that user.
