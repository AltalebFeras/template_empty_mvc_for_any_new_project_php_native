<?php

namespace App\Middleware;

use App\Services\Config;
use App\Services\ConfigRouter;

/**
 * Security Headers Middleware
 *
 * Sets defense-in-depth HTTP response headers on every request.
 * These headers instruct browsers to enforce security policies that
 * mitigate XSS, clickjacking, MIME sniffing, and other client-side attacks.
 */
final class SecurityHeaders
{
    /**
     * Sends all security headers. Call early in the request lifecycle.
     */
    public static function send(): void
    {
        // Prevent MIME-type sniffing (mitigates XSS via type confusion).
        header('X-Content-Type-Options: nosniff');

        // Clickjacking protection — blocks this site from being framed.
        header('X-Frame-Options: SAMEORIGIN');

        // Control Referer header leakage.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Disable unused browser features.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');

        // Remove PHP version fingerprint.
        header_remove('X-Powered-By');

        // --- HSTS (production only, requires HTTPS) ---
        if (Config::isProduction()) {
            // 1 year + subdomains + preload-ready
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // --- Content Security Policy ---
        // Strict by default. Projects should adjust this per their external dependencies.
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",       // inline styles needed for email templates etc.
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-src 'self' https://challenges.cloudflare.com",  // Turnstile iframe
            "child-src 'self' https://challenges.cloudflare.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        // Only upgrade insecure requests if the request is served over HTTPS
        if (ConfigRouter::isHttps()) {
            $cspDirectives[] = "upgrade-insecure-requests";
        }

        header('Content-Security-Policy: ' . implode('; ', $cspDirectives));

        // --- Cross-Origin policies ---
        // COOP requires a trustworthy origin (HTTPS or localhost)
        if (ConfigRouter::isHttps()) {
            header('Cross-Origin-Opener-Policy: same-origin');
        }
        header('Cross-Origin-Resource-Policy: same-origin');
    }
}
