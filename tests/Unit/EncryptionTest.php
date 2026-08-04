<?php

namespace Tests\Unit;

use App\Services\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AES-256-GCM Encryption service.
 */
class EncryptionTest extends TestCase
{
    private Encryption $enc;

    protected function setUp(): void
    {
        // Use a fixed test key (32 bytes).
        $key = random_bytes(32);
        $this->enc = new Encryption($key);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'Hello, World! 🚀';
        $cipher    = $this->enc->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $cipher);
        $this->assertSame($plaintext, $this->enc->decrypt($cipher));
    }

    public function testEncryptProducesUniqueOutputs(): void
    {
        $plaintext = 'same input';
        $a = $this->enc->encrypt($plaintext);
        $b = $this->enc->encrypt($plaintext);

        // Same plaintext should produce different ciphertexts (unique IV).
        $this->assertNotSame($a, $b);

        // Both should decrypt to the same value.
        $this->assertSame($plaintext, $this->enc->decrypt($a));
        $this->assertSame($plaintext, $this->enc->decrypt($b));
    }

    public function testDecryptRejectsTamperedData(): void
    {
        $cipher = $this->enc->encrypt('secret');

        // Tamper with the payload.
        $tampered = $cipher . 'X';
        $this->assertFalse($this->enc->decrypt($tampered));
    }

    public function testDecryptRejectsEmptyString(): void
    {
        $this->assertFalse($this->enc->decrypt(''));
    }

    public function testDecryptRejectsGarbage(): void
    {
        $this->assertFalse($this->enc->decrypt('not-a-valid-payload'));
    }

    public function testEncryptDecryptId(): void
    {
        $id     = 42;
        $cipher = $this->enc->encryptId($id);
        $result = $this->enc->decryptId($cipher);

        $this->assertSame('42', $result);
    }

    public function testEncryptDecryptStringId(): void
    {
        $id     = 'abc-123-def';
        $cipher = $this->enc->encryptId($id);
        $result = $this->enc->decryptId($cipher);

        $this->assertSame('abc-123-def', $result);
    }

    public function testDifferentKeysCannotDecrypt(): void
    {
        $enc2   = new Encryption(random_bytes(32));
        $cipher = $this->enc->encrypt('only for key 1');

        $this->assertFalse($enc2->decrypt($cipher));
    }
}
