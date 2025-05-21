<?php

namespace src\Services;

class Encrypt_decrypt
{

    /**
     * Summary of encryptId
     * 
     * Securely encrypts an ID using AES-256-CBC encryption.
     * 
     * - A 256-bit key is used to encrypt the data.
     * - A random initialization vector (IV) is generated to ensure encryption uniqueness.
     * - The encrypted data and IV are encoded into a JSON object.
     * - The payload is then base64-encoded and URL-encoded for safe transmission.
     * 
     * @param mixed $id The ID to encrypt
     * @return string The encrypted and encoded string
     */
    public function encryptId($id): string
    {
        $key = ENCRYPTION_KEY;
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($id, 'aes-256-cbc', $key, 0, $iv);
        $payload = json_encode([
            'iv' => base64_encode($iv),
            'data' => $encrypted
        ]);
        return urlencode(base64_encode($payload));
    }

    /**
     * Summary of decryptId
     * 
     * Decrypts an ID that was previously encrypted using the encryptId method.
     * 
     * - The input string is first URL-decoded and then base64-decoded to retrieve the JSON payload.
     * - The JSON must contain both 'iv' and 'data' keys.
     * - The IV is base64-decoded and used along with the same encryption key to decrypt the data.
     * - Returns the original ID if successful, or false on failure (e.g., invalid format or tampered data).
     * 
     * @param mixed $data The encrypted and encoded string to decrypt
     * @return bool|string Returns the decrypted ID as a string or false if decryption fails
     */

    public function decryptId($data): bool|string
    {
        $key = ENCRYPTION_KEY;
        $decoded = base64_decode(urldecode($data), true);
        if ($decoded === false) return false;

        $json = json_decode($decoded, true);
        if (!isset($json['iv'], $json['data'])) return false;

        return openssl_decrypt($json['data'], 'aes-256-cbc', $key, 0, base64_decode($json['iv']));
    }

}
