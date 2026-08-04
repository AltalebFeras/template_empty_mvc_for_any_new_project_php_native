# Shared Hosting Deployment Guide (cPanel / Plesk / Hostinger / OVH)

This framework is built using **native PHP** and does **NOT** require Docker. It is 100% compatible with any standard shared hosting provider (cPanel, Plesk, Hostinger, Namecheap, OVH, GoDaddy, etc.) running PHP 8.2+ and MySQL.

---

## Shared Hosting Requirements

| Requirement | Details |
|-------------|---------|
| **PHP Version** | 8.2 or 8.3 |
| **Database** | MySQL 8.0+ or MariaDB 10.4+ |
| **PHP Extensions** | `pdo_mysql`, `openssl`, `mbstring`, `curl`, `gd`, `fileinfo`, `session` (all standard on shared hosts) |
| **Web Server** | Apache / LiteSpeed (uses `public/.htaccess`) |

---

## Deployment Steps

### Option A: Hosting allows setting Document Root to `public/` (Recommended)

Most modern cPanel / Plesk hosting accounts allow changing your domain's Document Root folder to `public/`.

#### Step 1: Upload Files via FTP / File Manager
Upload the entire project folder to your server (e.g. into `/home/username/myproject/` or above `public_html`).

```
/home/username/
├── myproject/                # Project root (outside web root)
│   ├── app/ (src)
│   ├── bin/
│   ├── logs/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── public/               # Set this as your domain's document root!
│       ├── .htaccess
│       ├── index.php
│       └── assets/
```

#### Step 2: Set Document Root
In cPanel / Plesk:
- Go to **Domains** / **Subdomains**.
- Change Document Root to `/home/username/myproject/public` (or `public_html/public`).

---

### Option B: Hosting forces Document Root to `public_html/`

If your provider locks your site to `/public_html/` and does not let you change it:

#### Step 1: Upload Files
Upload all framework files and folders directly inside `public_html/`:

```
public_html/
├── bin/
├── logs/
├── src/
├── storage/
├── vendor/
├── .env                  # Protected from web access by root .htaccess
├── .htaccess             # Root htaccess redirects to public/
└── public/
    ├── .htaccess
    ├── index.php
    └── assets/
```

#### Step 2: Create Root `.htaccess` (inside `public_html/`)
Create a `.htaccess` file directly inside `public_html/` to route all incoming requests to the `public/` subfolder securely:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Prevent direct HTTP access to protected directories
    RewriteRule ^(src|vendor|storage|logs|bin|\.env) - [F,L]

    # Rewrite all traffic to public/ subfolder
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

---

## Step 3: Database & `.env` Setup

1. **Create MySQL Database**:
   - Open cPanel **MySQL Database Wizard**.
   - Create a database, database user, and assign full privileges.

2. **Run Migrations / Import SQL**:
   - In cPanel Terminal (if available):
     ```bash
     php bin/migrate.php up
     ```
   - *Or via phpMyAdmin*: Execute the SQL statements from `src/Migrations/`.

3. **Configure `.env` File**:
   Create a `.env` file in your project root with your production credentials:

   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://yourdomain.com
   APP_KEY=generate_64_hex_chars_here

   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=yourcpanel_dbname
   DB_USER=yourcpanel_dbuser
   DB_PASS=yourcpanel_dbpass

   SESSION_LIFETIME=7200
   SESSION_IDLE_TIMEOUT=1800
   ```

   Generate `APP_KEY` locally using:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

---

## Step 4: Folder Permissions

Ensure `storage/` and `logs/` are writable by the server:

- Set permissions on `storage/` and `logs/` to `755` (or `775` / `777` if required by host).

---

## Features That Work Out-of-the-Box on Shared Hosting

| Feature | How it works on Shared Hosting |
|---------|--------------------------------|
| **Cache** | Automatic file-based fallback in `storage/cache/` (no Redis needed!) |
| **Session** | Native PHP session files with strict cookie security |
| **Security Headers** | Sent via PHP `header()` & Apache `public/.htaccess` |
| **CSRF & Validation** | Handled natively in PHP |
| **File Uploads** | Saved to `storage/uploads/` |
| **Cron / Jobs** | Add a cPanel Cron Job for `php /path/to/bin/worker.php --max=50` every 5 minutes |
