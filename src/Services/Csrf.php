<?php

namespace src\Services;

/**
 * CSRF Protection Service
 *
 * Implements the Synchronizer Token Pattern:
 * - One cryptographically random token is generated per session.
 * - Every state-changing request (POST, PUT, PATCH, DELETE) must carry it.
 * - The router validates it automatically via Csrf::validateToken().
 *
 * Usage in views (add to every HTML form):
 *   <?= Csrf::inputField() ?>
 *
 * Usage in AJAX (add to every fetch/axios request header):
 *   headers: { 'X-CSRF-Token': '<?= Csrf::getToken() ?>' }
 *
 * Manual validation (if needed outside the router):
 *   if (!Csrf::validateToken($_POST['_csrf_token'] ?? '')) { ... }
 */
class Csrf
{
    private const TOKEN_KEY    = '_csrf_token';
    private const TOKEN_LENGTH = 32; // bytes → 64 hex chars

    /**
     * Returns the current session CSRF token, generating one if it does not exist yet.
     */
    public static function getToken(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Validates a submitted token against the session token.
     *
     * Uses hash_equals() to prevent timing-based side-channel attacks.
     *
     * @param string $submittedToken Token from $_POST['_csrf_token'] or request header.
     * @return bool True if valid, false otherwise.
     */
    public static function validateToken(string $submittedToken): bool
    {
        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';

        if ($sessionToken === '' || $submittedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Rotates the token. Call after a successful login or privilege escalation
     * to prevent session fixation via CSRF token.
     */
    public static function refreshToken(): string
    {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Returns a ready-to-embed hidden input HTML element containing the current token.
     *
     * @return string HTML string — safe to echo directly inside a <form>.
     */
    public static function inputField(): string
    {
        $token = htmlspecialchars(self::getToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }
}
