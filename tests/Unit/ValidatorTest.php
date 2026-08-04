<?php

namespace Tests\Unit;

use App\Services\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Validator service.
 */
class ValidatorTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Individual validators
    // -----------------------------------------------------------------------

    public function testIsEmail(): void
    {
        $this->assertTrue(Validator::isEmail('user@example.com'));
        $this->assertTrue(Validator::isEmail('user+tag@sub.domain.co'));
        $this->assertFalse(Validator::isEmail(''));
        $this->assertFalse(Validator::isEmail('not-an-email'));
        $this->assertFalse(Validator::isEmail('@missing-local.com'));
    }

    public function testIsNotEmpty(): void
    {
        $this->assertTrue(Validator::isNotEmpty('hello'));
        $this->assertTrue(Validator::isNotEmpty('0'));
        $this->assertFalse(Validator::isNotEmpty(''));
        $this->assertFalse(Validator::isNotEmpty('   '));
    }

    public function testMinLength(): void
    {
        $this->assertTrue(Validator::minLength('hello', 5));
        $this->assertTrue(Validator::minLength('hello', 3));
        $this->assertFalse(Validator::minLength('hi', 3));
        // Unicode: 3 characters, not 9 bytes.
        $this->assertTrue(Validator::minLength('日本語', 3));
    }

    public function testMaxLength(): void
    {
        $this->assertTrue(Validator::maxLength('hi', 5));
        $this->assertTrue(Validator::maxLength('hello', 5));
        $this->assertFalse(Validator::maxLength('hello!', 5));
    }

    public function testIsUrl(): void
    {
        $this->assertTrue(Validator::isUrl('https://example.com'));
        $this->assertTrue(Validator::isUrl('http://localhost:8080/path'));
        $this->assertFalse(Validator::isUrl('not-a-url'));
        $this->assertFalse(Validator::isUrl(''));
    }

    public function testIsDate(): void
    {
        $this->assertTrue(Validator::isDate('2024-01-15'));
        $this->assertFalse(Validator::isDate('2024-13-01')); // Invalid month.
        $this->assertFalse(Validator::isDate('not-a-date'));
    }

    public function testIsAlpha(): void
    {
        $this->assertTrue(Validator::isAlpha('Hello'));
        $this->assertTrue(Validator::isAlpha('Héllo')); // Accented chars are letters.
        $this->assertFalse(Validator::isAlpha('Hello123'));
        $this->assertFalse(Validator::isAlpha(''));
    }

    public function testIsSlug(): void
    {
        $this->assertTrue(Validator::isSlug('hello-world'));
        $this->assertTrue(Validator::isSlug('post123'));
        $this->assertFalse(Validator::isSlug('Hello-World')); // Uppercase.
        $this->assertFalse(Validator::isSlug('hello--world')); // Double dash.
    }

    public function testEscape(): void
    {
        $this->assertSame('&lt;script&gt;', Validator::escape('<script>'));
        $this->assertSame('&quot;quotes&quot;', Validator::escape('"quotes"'));
        $this->assertSame('&#039;single&#039;', Validator::escape("'single'"));
    }

    // -----------------------------------------------------------------------
    // Batch validation
    // -----------------------------------------------------------------------

    public function testValidateRequired(): void
    {
        $errors = Validator::validate(
            ['name' => '', 'email' => 'test@example.com'],
            ['name' => ['required'], 'email' => ['required', 'email']]
        );

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayNotHasKey('email', $errors);
    }

    public function testValidateMinMax(): void
    {
        $errors = Validator::validate(
            ['password' => 'short'],
            ['password' => ['required', 'min:8', 'max:128']]
        );

        $this->assertArrayHasKey('password', $errors);
        $this->assertStringContainsString('at least 8', $errors['password']);
    }

    public function testValidateIn(): void
    {
        $errors = Validator::validate(
            ['role' => 'superadmin'],
            ['role' => ['required', 'in:user,editor,admin']]
        );

        $this->assertArrayHasKey('role', $errors);
    }

    public function testValidateConfirmed(): void
    {
        $errors = Validator::validate(
            ['password' => 'secret123', 'password_confirmation' => 'different'],
            ['password' => ['confirmed']]
        );

        $this->assertArrayHasKey('password', $errors);
    }

    public function testValidateCustomMessages(): void
    {
        $errors = Validator::validate(
            ['email' => ''],
            ['email' => ['required']],
            ['email.required' => 'Please enter your email.']
        );

        $this->assertSame('Please enter your email.', $errors['email']);
    }

    public function testValidatePassesWithValidData(): void
    {
        $errors = Validator::validate(
            [
                'name'  => 'John Doe',
                'email' => 'john@example.com',
                'age'   => '25',
            ],
            [
                'name'  => ['required', 'min:2', 'max:100'],
                'email' => ['required', 'email'],
                'age'   => ['required', 'int'],
            ]
        );

        $this->assertEmpty($errors);
    }
}
