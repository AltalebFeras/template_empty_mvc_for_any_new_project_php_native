<?php

namespace App\Services;

/**
 * CSRF Protection Service — Enhanced with per-form tokens and expiry.
 *
 * Implements the Synchronizer Token Pattern with two modes:
 *   1. Global session token (backward-compatible, default)
 *   2. Per-form scoped tokens with configurable TTL
 *
 * Usage in views:
 *   <?= Csrf::inputField() ?>              — global token
 *   <?= Csrf::inputField('login') ?>       — form-scoped token
 *
 * Usage in AJAX:
 *   headers: { 'X-CSRF-Token': '<?= Csrf::getToken() ?>' }
 *
 * Validation:
 *   Csrf::validateToken($submittedToken);              — global
 *   Csrf::validateToken($submittedToken, 'login');     — form-scoped
 */
final class Csrf
{
    private const TOKEN_KEY       = '_csrf_token';
    private const TOKEN_STORE_KEY = '_csrf_tokens';
    private const TOKEN_LENGTH    = 32; // bytes → 64 hex chars
    private const DEFAULT_TTL     = 3600; // 1 hour

    // -----------------------------------------------------------------------
    // Global token (backward-compatible)
    // -----------------------------------------------------------------------

    /**
     * Returns the current session CSRF token, generating one if needed.
     * If a $formId is provided, returns a form-scoped token instead.
     *
     * @param string|null $formId Optional form identifier for scoped tokens.
     */
    public static function getToken(?string $formId = null): string
    {
        if ($formId !== null) {
            return self::getFormToken($formId);
        }

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
     * @param string      $submittedToken Token from POST or header.
     * @param string|null $formId         Optional form identifier for scoped validation.
     * @return bool True if valid, false otherwise.
     */
    public static function validateToken(string $submittedToken, ?string $formId = null): bool
    {
        if ($formId !== null) {
            return self::validateFormToken($submittedToken, $formId);
        }

        $sessionToken = $_SESSION[self::TOKEN_KEY] ?? '';

        if ($sessionToken === '' || $submittedToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Rotates the global token. Call after login or privilege escalation.
     */
    public static function refreshToken(): string
    {
        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * Returns a hidden input element with the CSRF token.
     *
     * @param string|null $formId Optional form identifier.
     * @return string HTML string safe to echo inside a <form>.
     */
    public static function inputField(?string $formId = null): string
    {
        $token = htmlspecialchars(self::getToken($formId), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html  = '<input type="hidden" name="_csrf_token" value="' . $token . '">';
        if ($formId !== null) {
            $formIdSafe = htmlspecialchars($formId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<input type="hidden" name="_csrf_form_id" value="' . $formIdSafe . '">';
        }
        return $html;
    }

    // -----------------------------------------------------------------------
    // Per-form scoped tokens with expiry
    // -----------------------------------------------------------------------

    /**
     * Generates or retrieves a time-limited token scoped to a specific form.
     */
    private static function getFormToken(string $formId): string
    {
        self::pruneExpiredTokens();

        if (!isset($_SESSION[self::TOKEN_STORE_KEY][$formId])) {
            $_SESSION[self::TOKEN_STORE_KEY][$formId] = [
                'token'      => bin2hex(random_bytes(self::TOKEN_LENGTH)),
                'expires_at' => time() + self::DEFAULT_TTL,
            ];
        }

        return $_SESSION[self::TOKEN_STORE_KEY][$formId]['token'];
    }

    /**
     * Validates a form-scoped token and consumes it (one-time use).
     */
    private static function validateFormToken(string $submittedToken, string $formId): bool
    {
        self::pruneExpiredTokens();

        $entry = $_SESSION[self::TOKEN_STORE_KEY][$formId] ?? null;

        if ($entry === null || $submittedToken === '') {
            return false;
        }

        // Check expiry.
        if (time() > $entry['expires_at']) {
            unset($_SESSION[self::TOKEN_STORE_KEY][$formId]);
            return false;
        }

        $valid = hash_equals($entry['token'], $submittedToken);

        // Consume the token after validation (one-time use).
        unset($_SESSION[self::TOKEN_STORE_KEY][$formId]);

        return $valid;
    }

    /**
     * Removes expired form tokens from the session to prevent unbounded growth.
     */
    private static function pruneExpiredTokens(): void
    {
        if (!isset($_SESSION[self::TOKEN_STORE_KEY])) {
            $_SESSION[self::TOKEN_STORE_KEY] = [];
            return;
        }

        $now = time();
        $_SESSION[self::TOKEN_STORE_KEY] = array_filter(
            $_SESSION[self::TOKEN_STORE_KEY],
            fn(array $entry) => $now <= $entry['expires_at']
        );
    }
}
