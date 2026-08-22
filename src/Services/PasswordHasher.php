<?php

namespace App\Services;

use RuntimeException;

/**
 * Secure password hashing using Argon2id.
 *
 * Argon2id is the recommended algorithm (OWASP, NIST) because it combines
 * resistance to both GPU-based (Argon2d) and side-channel (Argon2i) attacks.
 *
 * Usage:
 *   $hash = PasswordHasher::hash($plainPassword);
 *   if (PasswordHasher::verify($plainPassword, $storedHash)) { ... }
 *   if (PasswordHasher::needsRehash($storedHash)) { rehash... }
 */
final class PasswordHasher
{
    /**
     * Cost parameters — these should be tuned to your server's capability.
     * The goal is ~0.5–1 second per hash on production hardware.
     *
     * - memory_cost: KiB of memory to use (64 MB)
     * - time_cost:   number of iterations (4)
     * - threads:     degree of parallelism (2)
     */
    private const OPTIONS = [
        'memory_cost' => 65536,  // 64 MB
        'time_cost'   => 4,
        'threads'     => 2,
    ];

    /**
     * Hashes a plaintext password using Argon2id.
     *
     * @param string $password Plaintext password.
     * @return string The hashed password string.
     * @throws RuntimeException If hashing fails.
     */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    /**
     * Verifies a plaintext password against a stored hash.
     *
     * Uses PHP's built-in constant-time comparison to prevent
     * timing side-channel attacks.
     *
     * @param string $password Plaintext password to verify.
     * @param string $hash     Stored hash to verify against.
     * @return bool True if the password matches the hash.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Checks if a stored hash needs to be rehashed.
     *
     * Returns true if the hash was created with an older algorithm or
     * weaker cost parameters than currently configured. Use after
     * successful login to transparently upgrade stored hashes.
     *
     * @param string $hash Stored hash to check.
     * @return bool True if the hash should be regenerated.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
