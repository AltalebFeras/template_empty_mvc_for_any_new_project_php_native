# Documentation Index

Comprehensive technical documentation for the Enterprise PHP MVC Framework Template.

## Table of Contents

| # | Document | Description |
|---|----------|-------------|
| 01 | [Getting Started](01-getting-started.md) | Installation, prerequisites, first-run setup |
| 02 | [Architecture](02-architecture.md) | Request lifecycle, directory structure, design patterns |
| 03 | [Configuration](03-configuration.md) | Environment variables, Config service API |
| 04 | [Routing](04-routing.md) | Attribute-based routing, HTTP method spoofing, route parameters |
| 05 | [Controllers](05-controllers.md) | AbstractController, rendering views, redirects |
| 06 | [Security](06-security.md) | Encryption, password hashing, CSRF, security headers |
| 07 | [Authentication](07-authentication.md) | Login flow, session management, session hardening |
| 08 | [Authorization](08-authorization.md) | RBAC/ABAC, roles, permissions, guards |
| 09 | [Database](09-database.md) | PDO singleton, AbstractRepository, migrations |
| 10 | [Validation](10-validation.md) | Validator rules, batch validation, custom messages |
| 11 | [API Development](11-api-development.md) | ApiResponse, CORS, rate limiting |
| 12 | [File Uploads](12-file-uploads.md) | Secure uploads, image processing, SVG sanitization |
| 13 | [Caching](13-caching.md) | Multi-tier cache, response compression, ETag |
| 14 | [Logging](14-logging.md) | Structured logging, request logger, threat logger |
| 15 | [Background Jobs](15-background-jobs.md) | Job queue, worker, retry strategy |
| 16 | [Resilience](16-resilience.md) | Circuit breaker, health checks |
| 17 | [Email](17-email.md) | Mail service, SMTP configuration, templates |
| 18 | [Docker & Deployment](18-docker-deployment.md) | Dockerfile, Compose, Nginx, OPcache |
| 19 | [Testing](19-testing.md) | PHPUnit, PHPStan, test writing guide |
| 20 | [Cloudflare Turnstile](20-turnstile.md) | Bot protection setup and verification |
| 21 | [Entities & Hydration](21-entities-hydration.md) | Entity pattern, Hydration trait |
| 22 | [Shared Hosting Deployment](22-shared-hosting-deployment.md) | Deployment guide for cPanel/Plesk without Docker |
| 23 | [Component Reference](23-component-reference.md) | Technical reference & usage for all framework classes, middleware & CLI scripts |
| — | [Security Runbook](../SECURITY.md) | Operational security procedures |

## Quick Links

- **New to the project?** Start with [Getting Started](01-getting-started.md)
- **Building a feature?** See [Controllers](05-controllers.md) → [Database](09-database.md) → [Validation](10-validation.md)
- **Deploying?** See [Docker & Deployment](18-docker-deployment.md)
- **Security concern?** See [Security](06-security.md) and the [Security Runbook](../SECURITY.md)
