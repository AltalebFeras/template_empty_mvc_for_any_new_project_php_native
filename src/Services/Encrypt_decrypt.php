<?php

namespace src\Services;

class Encrypt_decrypt
{
    private string $cipher = 'aes-256-cbc';

    /**
     * Derives a fixed 32-byte key from the ENCRYPTION_KEY constant using SHA-256.
     * This ensures the key is always the correct length for AES-256, regardless
     * of the passphrase length or character set.
     */
    private function getKey(): string
    {
        return hash('sha256', ENCRYPTION_KEY, true);
    }

    /**
     * Encrypts any value using AES-256-CBC.
     *
     * A unique IV is generated per encryption call to ensure no two ciphertexts
     * are identical even for the same input. The result is a URL-safe base64
     * string carrying both the IV and the ciphertext.
     *
     * @param int|string $id The value to encrypt (typically a record ID).
     * @return string URL-safe base64-encoded encrypted payload.
     */
    public function encryptId(int|string $id): string
    {
        $key    = $this->getKey();
        $ivLen  = openssl_cipher_iv_length($this->cipher);
        $iv     = openssl_random_pseudo_bytes($ivLen);

        $encrypted = openssl_encrypt((string) $id, $this->cipher, $key, 0, $iv);

        $payload = json_encode([
            'iv'   => base64_encode($iv),
            'data' => $encrypted,
        ]);

        return urlencode(base64_encode($payload));
    }

    /**
     * Decrypts a payload produced by encryptId().
     *
     * Returns the original value as a string, or false if the payload is
     * malformed, tampered with, or decryption fails.
     *
     * @param string $data URL-safe base64-encoded encrypted payload.
     * @return string|false Decrypted string, or false on failure.
     */
    public function decryptId(string $data): string|false
    {
        $decoded = base64_decode(urldecode($data), true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);
        if (!isset($json['iv'], $json['data'])) {
            return false;
        }

        $key = $this->getKey();
        $iv  = base64_decode($json['iv'], true);
        if ($iv === false) {
            return false;
        }

        $result = openssl_decrypt($json['data'], $this->cipher, $key, 0, $iv);

        return $result;
    }
}
