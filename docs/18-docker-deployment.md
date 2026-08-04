# Docker & Deployment

Covers multi-stage Docker builds, Docker Compose stack, production Nginx vhost configuration, and deployment procedures.

**Source files:** `Dockerfile`, `docker-compose.yml`, `docker/nginx/default.conf`, `opcache.ini`

---

## Docker Stack Architecture

```
Internet / Reverse Proxy
         │ (HTTP 8080 / HTTPS 443)
         ▼
 ┌───────────────┐
 │ Nginx 1.25    │  Web Server (Static assets + FastCGI proxy)
 └───────┬───────┘
         │ (FastCGI :9000)
         ▼
 ┌───────────────┐
 │ PHP-FPM 8.2   │  Application container (Non-root user `appuser`)
 └───────┬───────┘
         ├──────────────────┬──────────────────┐
         ▼                  ▼                  ▼
 ┌───────────────┐  ┌───────────────┐  ┌───────────────┐
 │ MySQL 8.0     │  │ Redis 7       │  │ Mailpit       │
 │ Data volume   │  │ Cache volume  │  │ Dev Mail      │
 └───────────────┘  └───────────────┘  └───────────────┘
```

---

## Multi-Stage Dockerfile

`Dockerfile` uses a 2-stage build to keep production images tiny and secure:

### Stage 1: Vendor (Composer)
- Base image: `composer:2`
- Runs `composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader`
- Discards build tools after vendor directory is generated

### Stage 2: App (PHP 8.2 Alpine)
- Base image: `php:8.2-fpm-alpine`
- Installs minimal system packages + PHP extensions: `pdo_mysql`, `pdo_pgsql`, `gd`, `opcache`, `mbstring`, `curl`, `xml`, `bcmath`, `redis`
- Copies OPcache config and production `php.ini`
- Creates unprivileged user (`appuser`, UID 1000)
- Copies codebase + vendor binaries with `chown=appuser:appuser`
- Runs as `USER appuser` (non-root execution for container security)

---

## Docker Compose Services

```bash
docker-compose up -d
```

| Service | Container Name | Port Mapping | Description |
|---------|----------------|--------------|-------------|
| `app` | `app-php` | 9000 (internal) | PHP 8.2 FPM process |
| `web` | `app-nginx` | 8080:80, 443:443 | Nginx HTTP server |
| `db` | `app-mysql` | 3306:3306 | MySQL 8.0 database (with healthcheck) |
| `redis` | `app-redis` | 6379:6379 | Redis 7 in-memory cache |
| `mailpit` | `app-mailpit` | 1025 (SMTP), 8025 (UI) | Dev mail catcher |

---

## Nginx Production Vhost (`default.conf`)

Key production configurations in `docker/nginx/default.conf`:

### Security Directives
- **Document Root**: Restricted to `/var/www/public`
- **Block Sensitive Directories**: Denies access to `vendor/`, `src/`, `bin/`, `logs/`, `storage/`, `docker/`
- **Block Hidden/Dotfiles**: Denies access to `.env`, `.git/`
- **Block PHP in Storage**: Denies `.php` execution inside `/storage/uploads/`
- **Hide PHP Version**: `server_tokens off`

### Static Asset Caching
```nginx
location ~* \.(css|js|jpg|jpeg|png|gif|webp|svg|ico|woff2?|ttf|map)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
    try_files $uri =404;
}
```

### Compression
Gzip enabled for `text/plain`, `text/css`, `application/json`, `application/javascript`, `image/svg+xml`.

---

## Deployment Checklist

1. **Environment Setup**:
   ```bash
   cp .env.example .env
   # Set APP_ENV=production, APP_DEBUG=false, APP_KEY=...
   ```
2. **Build and Run Containers**:
   ```bash
   docker-compose -f docker-compose.yml build --no-cache
   docker-compose up -d
   ```
3. **Run Migrations**:
   ```bash
   docker-compose exec app php bin/migrate.php up
   ```
4. **Set Permissions**:
   ```bash
   docker-compose exec app chown -R appuser:appuser logs storage
   ```
5. **Verify Probes**:
   ```bash
   curl -f http://localhost:8080/health
   curl -f http://localhost:8080/ready
   ```
