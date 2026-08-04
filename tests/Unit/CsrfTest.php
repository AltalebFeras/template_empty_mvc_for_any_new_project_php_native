<?php

namespace Tests\Unit;

use App\Services\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CSRF protection service.
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Simulate a session.
        if (session_status() === PHP_SESSION_NONE) {
            $_SESSION = [];
        } else {
            $_SESSION = [];
        }
    }

    public function testGetTokenGeneratesToken(): void
    {
        $token = Csrf::getToken();

        $this->assertNotEmpty($token);
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars.
    }

    public function testGetTokenReturnsSameTokenPerSession(): void
    {
        $token1 = Csrf::getToken();
        $token2 = Csrf::getToken();

        $this->assertSame($token1, $token2);
    }

    public function testValidateTokenAcceptsValidToken(): void
    {
        $token = Csrf::getToken();

        $this->assertTrue(Csrf::validateToken($token));
    }

    public function testValidateTokenRejectsInvalidToken(): void
    {
        Csrf::getToken(); // Generate a token first.

        $this->assertFalse(Csrf::validateToken('invalid-token'));
    }

    public function testValidateTokenRejectsEmptyToken(): void
    {
        $this->assertFalse(Csrf::validateToken(''));
    }

    public function testRefreshTokenGeneratesNewToken(): void
    {
        $original  = Csrf::getToken();
        $refreshed = Csrf::refreshToken();

        $this->assertNotSame($original, $refreshed);
        $this->assertSame($refreshed, Csrf::getToken());
    }

    public function testInputFieldContainsToken(): void
    {
        $html = Csrf::inputField();

        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString(Csrf::getToken(), $html);
    }

    public function testFormScopedTokenIsDifferentFromGlobal(): void
    {
        $globalToken = Csrf::getToken();
        $formToken   = Csrf::getToken('login');

        $this->assertNotSame($globalToken, $formToken);
    }

    public function testFormScopedTokenValidation(): void
    {
        $formToken = Csrf::getToken('register');

        $this->assertTrue(Csrf::validateToken($formToken, 'register'));
    }

    public function testFormScopedTokenIsConsumedAfterValidation(): void
    {
        $formToken = Csrf::getToken('contact');

        // First validation should succeed.
        $this->assertTrue(Csrf::validateToken($formToken, 'contact'));

        // Second validation should fail (token consumed).
        $this->assertFalse(Csrf::validateToken($formToken, 'contact'));
    }

    public function testFormScopedInputFieldIncludesFormId(): void
    {
        $html = Csrf::inputField('login');

        $this->assertStringContainsString('name="_csrf_form_id"', $html);
        $this->assertStringContainsString('value="login"', $html);
    }
}
