<?php

namespace App\Services;

use RuntimeException;

/**
 * Authenticated Encryption Service — AES-256-GCM (AEAD).
 *
 * Replaces the old AES-256-CBC implementation which lacked authentication
 * and was vulnerable to padding oracle attacks.
 *
 * AES-256-GCM provides both confidentiality and integrity in a single
 * operation — the authentication tag is verified during decryption and
 * any tampering causes an immediate failure.
 *
 * Usage:
 *   $enc = new Encryption();
 *   $cipher = $enc->encrypt('sensitive data');
 *   $plain  = $enc->decrypt($cipher);  // returns string|false
 */
final class Encryption
{
    private const CIPHER   = 'aes-256-gcm';
    private const TAG_LEN  = 16; // GCM auth tag length in bytes
    private const IV_LEN   = 12; // GCM recommended nonce length

    private string $key;

    /**
     * @param string|null $key Raw 32-byte key. Defaults to APP_KEY from .env.
     */
    public function __construct(?string $key = null)
    {
        $this->key = $key ?? $this->deriveKey();
    }

    /**
     * Encrypts a plaintext value using AES-256-GCM.
     *
     * Returns a URL-safe base64 string containing IV + tag + ciphertext.
     *
     * @param string $plaintext The value to encrypt.
     * @return string URL-safe base64-encoded payload.
     * @throws RuntimeException If encryption fails.
     */
    public function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',          // AAD (additional authenticated data)
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Pack: IV (12) + Tag (16) + Ciphertext (variable)
        $payload = $iv . $tag . $ciphertext;

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * Decrypts a payload produced by encrypt().
     *
     * The GCM authentication tag is verified internally by OpenSSL.
     * Returns false if the payload is malformed, tampered with, or
     * decryption fails for any reason.
     *
     * @param string $payload URL-safe base64-encoded encrypted payload.
     * @return string|false Decrypted plaintext, or false on failure.
     */
    public function decrypt(string $payload): string|false
    {
        $raw = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($raw === false) {
            return false;
        }

        $minLen = self::IV_LEN + self::TAG_LEN + 1;
        if (strlen($raw) < $minLen) {
            return false;
        }

        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext; // false if tag verification fails
    }

    /**
     * Convenience method: encrypts a record ID for safe URL embedding.
     */
    public function encryptId(int|string $id): string
    {
        return $this->encrypt((string) $id);
    }

    /**
     * Convenience method: decrypts a record ID from a URL parameter.
     */
    public function decryptId(string $data): string|false
    {
        return $this->decrypt($data);
    }

    /**
     * Derives the AES-256 key from the APP_KEY environment variable
     * using HKDF (Hash-based Key Derivation Function).
     *
     * HKDF provides proper domain separation and key stretching,
     * unlike raw hash('sha256', ...) which lacks these properties.
     */
    private function deriveKey(): string
    {
        $masterKey = Config::getEncryptionKey();

        $derived = hash_hkdf('sha256', $masterKey, 32, 'aes-256-gcm-encryption');
        if ($derived === false) {
            throw new RuntimeException('Key derivation (HKDF) failed.');
        }

        return $derived;
    }
}
