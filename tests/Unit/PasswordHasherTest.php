<?php

namespace Tests\Unit;

use App\Services\PasswordHasher;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Argon2id Password Hasher.
 */
class PasswordHasherTest extends TestCase
{
    public function testHashProducesArgon2idHash(): void
    {
        $hash = PasswordHasher::hash('MyP@ssw0rd!');

        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testVerifyCorrectPassword(): void
    {
        $password = 'CorrectHorseBatteryStaple';
        $hash     = PasswordHasher::hash($password);

        $this->assertTrue(PasswordHasher::verify($password, $hash));
    }

    public function testVerifyWrongPassword(): void
    {
        $hash = PasswordHasher::hash('RealPassword');

        $this->assertFalse(PasswordHasher::verify('WrongPassword', $hash));
    }

    public function testHashProducesUniqueResults(): void
    {
        $password = 'SamePassword';
        $hash1    = PasswordHasher::hash($password);
        $hash2    = PasswordHasher::hash($password);

        // Each hash should be unique due to random salt.
        $this->assertNotSame($hash1, $hash2);

        // Both should verify correctly.
        $this->assertTrue(PasswordHasher::verify($password, $hash1));
        $this->assertTrue(PasswordHasher::verify($password, $hash2));
    }

    public function testNeedsRehashForBcrypt(): void
    {
        // A bcrypt hash should need rehashing to Argon2id.
        $bcryptHash = password_hash('test', PASSWORD_BCRYPT);

        $this->assertTrue(PasswordHasher::needsRehash($bcryptHash));
    }

    public function testNeedsRehashForCurrentHash(): void
    {
        $hash = PasswordHasher::hash('test');

        // A freshly generated Argon2id hash should NOT need rehashing.
        $this->assertFalse(PasswordHasher::needsRehash($hash));
    }

    public function testEmptyPasswordCanBeHashed(): void
    {
        // Empty passwords should still be hashable (validation is elsewhere).
        $hash = PasswordHasher::hash('');

        $this->assertTrue(PasswordHasher::verify('', $hash));
        $this->assertFalse(PasswordHasher::verify('non-empty', $hash));
    }

    public function testUnicodePasswordSupport(): void
    {
        $password = '日本語パスワード🔒';
        $hash     = PasswordHasher::hash($password);

        $this->assertTrue(PasswordHasher::verify($password, $hash));
        $this->assertFalse(PasswordHasher::verify('different', $hash));
    }
}
