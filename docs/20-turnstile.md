# Cloudflare Turnstile Integration

Covers bot protection setup, frontend widget rendering, and server-side API verification.

**Source files:** `src/Services/Turnstile.php`, `src/Views/includes/turnstile.php`

---

## Overview

[Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) is a user-friendly, privacy-preserving alternative to CAPTCHA that protects forms from automated bots and brute-force submissions.

```
Browser                          Server                       Cloudflare API
  │                                │                                │
  │── Render Turnstile Widget ────▶│                                │
  │   (cf-turnstile-response token)│                                │
  │                                │                                │
  │── Submit Form + Token ────────▶│                                │
  │                                │── POST /siteverify (token) ───▶│
  │                                │◀── JSON { success: true } ─────│
  │◀── Process / Reject ───────────│                                │
```

---

## Setup & Configuration

### 1. Obtain Keys

Create a widget in your [Cloudflare Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile):
- **Site Key** (Public): Embed in frontend views
- **Secret Key** (Private): Server-side API verification

### 2. Configure `.env`

```env
TURNSTILE_SITE_KEY=0x4AAAAAAAX...
TURNSTILE_SECRET_KEY=0x4AAAAAAAX...
```

> 💡 **Development Bypass**: If `TURNSTILE_SECRET_KEY` is omitted in non-production (`APP_ENV != production`), verification automatically returns `success: true` to prevent blocking local development.

---

## Frontend Integration

Include the reusable partial inside any HTML `<form>`:

```php
<form method="POST" action="/login">
    <?= \App\Services\Csrf::inputField('login') ?>

    <input type="email" name="email" required>
    <input type="password" name="password" required>

    <!-- Cloudflare Turnstile Widget -->
    <?php include __DIR__ . '/../includes/turnstile.php'; ?>

    <button type="submit">Log In</button>
</form>
```

### View Partial Code (`src/Views/includes/turnstile.php`)

The partial auto-detects if `TURNSTILE_SITE_KEY` is set and renders:

```html
<div class="cf-turnstile"
     data-sitekey="YOUR_SITE_KEY"
     data-theme="auto"
     data-size="normal">
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
```

---

## Server-Side Verification

Call `Turnstile::verify()` in your controller before processing form data:

```php
use App\Services\Turnstile;

#[Route('/login', methods: ['POST'])]
public function processLogin(): void
{
    $token  = $_POST['cf-turnstile-response'] ?? '';
    $userIp = $_SERVER['REMOTE_ADDR'] ?? null;

    $result = Turnstile::verify($token, $userIp);

    if ($result->failed()) {
        // $result->errorCodes contains array of failures
        $this->redirect('login', [], ['Bot verification failed. Please try again.']);
    }

    // Proceed with authentication...
}
```

---

## Security Verification Checks

The `Turnstile` service validates 3 criteria:

1. **API Challenge Success**: Cloudflare siteverify endpoint returns `success: true`.
2. **Hostname Validation**: Returned `hostname` must match `APP_URL` host.
3. **Token Freshness**: Challenge timestamp (`challenge_ts`) must be $\le$ 300 seconds (5 minutes) old.
