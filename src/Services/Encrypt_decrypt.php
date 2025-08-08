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


// how it works 

// Your encryption key (AES-256-CBC)
$key = 'd93vNg48Bqv7ZpxlA3YtP1mWcK0rJx9h';

// Your encrypted payloads example
$payloads = [
    'eyJpdiI6ImVQTTlIcmV2MnpiM1hCWE85ck93RUE9PSIsImRhdGEiOiIyU25SdTlySE5DRDdyV2d4TWt6MDBnPT0ifQ==',
    'eyJpdiI6IlwvY0diZHpvcnhqd3B3N2VoZWJ6TDJnPT0iLCJkYXRhIjoiejNndGV4VzdHZnRFbUtCYlFFZXI1Zz09In0='
];

// Function to decrypt a Laravel-style payload
function decryptPayload($payload, $key) {
    // Decode Base64 JSON
    $data = json_decode(base64_decode($payload), true);

    if (!isset($data['iv']) || !isset($data['data'])) {
        return null; // invalid format
    }

    // Replace escaped slashes in IV
    $iv = base64_decode(str_replace('\\/', '/', $data['iv']));
    $ciphertext = base64_decode($data['data']);

    // Decrypt using AES-256-CBC
    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    // Show the raw decrypted text
    echo "Raw decrypted value: " . $plaintext . PHP_EOL;

    return (int) $plaintext; // Convert to integer
}

// Process all payloads
foreach ($payloads as $index => $payload) {
    echo "Processing payload " . ($index + 1) . "..." . PHP_EOL;
    $userId = decryptPayload($payload, $key);
    echo "User ID (as integer): " . $userId . PHP_EOL;
    echo str_repeat("-", 30) . PHP_EOL;
}