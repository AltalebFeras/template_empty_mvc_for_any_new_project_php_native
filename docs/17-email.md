# Email Service (`Mail`)

Covers PHPMailer integration, SMTP configuration, table-based HTML email templates, attachments, and error logging.

**Source file:** `src/Services/Mail.php`

---

## Overview

The `Mail` service wraps [PHPMailer](https://github.com/PHPMailer/PHPMailer) to deliver HTML emails built with cross-client table-based markup.

### Why Table-Based Markup?

Major desktop and mobile email clients (Outlook Word engine, Gmail, Apple Mail, Yahoo) ignore modern CSS layout (`flex`, `grid`, external `<style>` tags). The `Mail` service uses nested `<table>` structures with 100% inline CSS and HTML presentation attributes to guarantee identical rendering across all inbox clients.

---

## Configuration

SMTP credentials are defined in `.env`:

```env
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls                  # tls | ssl | ''
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=MyApplication
```

### Encryption Behavior

- **Production (`APP_ENV=production`)**: Enforces SMTPS / STARTTLS based on `MAIL_ENCRYPTION`.
- **Development (`APP_ENV=development`)**: Allows unencrypted local relays (Mailpit / Mailtrap).

---

## Usage

### Sending an Email

```php
use App\Services\Mail;
use App\Services\Config;

$mailer = new Mail();

$mailer->sendEmail(
    from: Config::get('MAIL_FROM_ADDRESS'),
    fromName: Config::get('MAIL_FROM_NAME'),
    to: 'recipient@example.com',
    toName: 'Jane Doe',
    subject: 'Welcome to Our Platform!',
    body: 'Thank you for signing up. Your account is now active.'
);
```

### Adding Attachments

```php
$mailer = new Mail();
$mailer->addAttachment('/path/to/document.pdf');
$mailer->sendEmail(...);
```

### Custom Headers

```php
$mailer->sendEmail(
    from: '...', fromName: '...', to: '...', toName: '...',
    subject: 'Urgent Notification',
    body: '...',
    headers: ['X-Priority' => '1']
);
```

---

## HTML Email Template Architecture

The template includes:

- **MSO Conditional Comments**: Fixes Outlook scaling bugs (`<xml><o:OfficeDocumentSettings>`).
- **Outer Wrapper Table**: 100% width, background color `#f4f4f9`.
- **Email Card Table**: Fixed 600px width (100% responsive on mobile).
- **Header**: Primary theme color (`#2A6174`) with logo.
- **Subject Banner**: High-contrast header section.
- **Body Content**: Clean typography with fallback system fonts (`Arial, Helvetica, sans-serif`).
- **Footer**: Dynamic copyright year + unsubscribe disclaimer.
- **Plain-Text AltBody**: Generated automatically via `strip_tags()` for non-HTML mail clients.

---

## Error Handling

If SMTP delivery fails, an exception is caught and logged to `logs/mail-YYYY-MM-DD.log`:

```json
{
    "message": "Mail send failed",
    "context": {
        "to": "recipient@example.com",
        "error": "SMTP Error: Could not authenticate."
    }
}
```

Then a `RuntimeException` is thrown to the caller.
