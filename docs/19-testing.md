# Testing & Static Analysis

Covers PHPUnit unit and integration testing, PHPStan static analysis, and test writing guidelines.

**Source files:** `phpunit.xml`, `phpstan.neon`, `tests/bootstrap.php`, `tests/Unit/*`

---

## PHPUnit Configuration (`phpunit.xml`)

Configured for PHPUnit 12+ with two test suites:

- `Unit`: Tests in `tests/Unit/` (isolated, fast, zero DB dependency)
- `Integration`: Tests in `tests/Integration/` (database, filesystem, external API tests)

Coverage source filtering includes `src/` and excludes `src/Views/` and `src/Migrations/`.

---

## Running Tests

```bash
# Run all test suites
composer test
# or
vendor/bin/phpunit --colors=always

# Run only Unit tests
vendor/bin/phpunit --testsuite Unit

# Run a single test file
vendor/bin/phpunit tests/Unit/EncryptionTest.php

# Run a specific test method
vendor/bin/phpunit --filter testEncryptDecryptRoundTrip
```

---

## Current Test Suite Overview

All 42 unit tests pass with 82 assertions:

| Test Class | File | Tests | Coverage |
|------------|------|-------|----------|
| `EncryptionTest` | `tests/Unit/EncryptionTest.php` | 8 | Roundtrip, IV uniqueness, tamper detection, invalid hex, ID encryption |
| `PasswordHasherTest` | `tests/Unit/PasswordHasherTest.php` | 8 | Argon2id formatting, verification, unique salts, rehash detection, Unicode |
| `ValidatorTest` | `tests/Unit/ValidatorTest.php` | 16 | All 18+ rules, batch validation, custom messages, escaping |
| `CsrfTest` | `tests/Unit/CsrfTest.php` | 10 | Generation, persistence, validation, refresh, form-scoped tokens, single-use |

---

## Static Analysis (PHPStan)

Configured at **Level 6** in `phpstan.neon`:

```neon
parameters:
    level: 6
    paths:
        - src
    excludePaths:
        - src/Views
        - src/Migrations
```

Run static analysis:

```bash
composer analyse
# or
vendor/bin/phpstan analyse src/ --level=6
```

---

## Writing a Unit Test

Create new tests in `tests/Unit/`:

```php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Setup code executed before each test
    }

    public function testFeatureBehavesAsExpected(): void
    {
        // Arrange
        $input = 'test';

        // Act
        $result = strtoupper($input);

        // Assert
        $this->assertSame('TEST', $result);
    }
}
```

---

## Test Bootstrap (`tests/bootstrap.php`)

The test bootstrap:
1. Loads Composer autoloader.
2. Initializes a test `.env` file with dummy `APP_KEY` and testing values if `.env` does not exist.
3. Sets up global superglobals (`$_SERVER['REMOTE_ADDR']`, `$_SERVER['REQUEST_METHOD']`) for CLI test execution.
