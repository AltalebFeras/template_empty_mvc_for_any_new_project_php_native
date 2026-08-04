# Input Validation

The `Validator` service provides both individual validation methods and a batch validation engine with 18+ rules and custom error messages.

**Source file:** `src/Services/Validator.php`

---

## Batch Validation

### Basic Usage

```php
use App\Services\Validator;

$errors = Validator::validate($_POST, [
    'email'    => ['required', 'email', 'max:255'],
    'password' => ['required', 'min:8', 'max:128'],
    'role'     => ['required', 'in:user,editor,admin'],
]);

if (!empty($errors)) {
    // $errors = ['password' => "The field 'password' must be at least 8 characters."]
    $this->redirect('register', [], $errors);
}
```

### Custom Error Messages

```php
$errors = Validator::validate($_POST, [
    'email'    => ['required', 'email'],
    'password' => ['required', 'min:8', 'confirmed'],
], [
    'email.required'      => 'Please enter your email address.',
    'email.email'         => 'This does not look like a valid email.',
    'password.required'   => 'A password is mandatory.',
    'password.min'        => 'Password must be at least 8 characters long.',
    'password.confirmed'  => 'Passwords do not match.',
]);
```

Message keys follow the format `{field}.{rule}`.

### Validation Behavior

- Stops at the **first error per field** (returns only one error message per field).
- Skips validation for empty optional fields (only `required` triggers on empty strings).
- Returns an empty array `[]` if all validation passes.

---

## Available Rules

| Rule | Parameter | Description | Example |
|------|-----------|-------------|---------|
| `required` | — | Field must not be empty (after trim) | `'required'` |
| `email` | — | Must be a valid email address | `'email'` |
| `min:N` | N (int) | Minimum string length (UTF-8 aware) | `'min:8'` |
| `max:N` | N (int) | Maximum string length (UTF-8 aware) | `'max:255'` |
| `int` | — | Must be a valid integer | `'int'` |
| `positiveInt` | — | Must be a positive integer (≥ 1) | `'positiveInt'` |
| `url` | — | Must be a valid URL | `'url'` |
| `date` | format (optional) | Must be a valid date | `'date'` or `'date:d/m/Y'` |
| `boolean` | — | Must be a boolean-like value | `'boolean'` |
| `alpha` | — | Letters only (Unicode-aware) | `'alpha'` |
| `alpha_num` | — | Letters and numbers only (Unicode-aware) | `'alpha_num'` |
| `slug` | — | Lowercase alphanumeric + hyphens | `'slug'` |
| `in:a,b,c` | values (CSV) | Must be one of the listed values | `'in:user,editor,admin'` |
| `regex:pattern` | regex | Must match the regex pattern | `'regex:/^[A-Z]{3}$/'` |
| `confirmed` | — | Must match `{field}_confirmation` | `'confirmed'` |

### Rule Details

#### `required`
Trims the value and checks it's not empty. An empty string or whitespace-only string fails.

#### `email`
Uses PHP's `FILTER_VALIDATE_EMAIL`. Accepts standard formats like `user@example.com`, `user+tag@sub.domain.co`.

#### `min:N` / `max:N`
Uses `mb_strlen()` with UTF-8 encoding, so multi-byte characters are counted correctly:
```php
Validator::minLength('日本語', 3); // true (3 characters, not 9 bytes)
```

#### `date`
Defaults to `Y-m-d` format. Custom format via parameter:
```php
['birth_date' => ['required', 'date:d/m/Y']]
```

#### `boolean`
Accepts: `true`, `false`, `0`, `1`, `'0'`, `'1'`, `'true'`, `'false'`, `'yes'`, `'no'`

#### `slug`
Matches: `hello-world`, `post-123`, `my-article`
Rejects: `Hello-World` (uppercase), `hello--world` (double dash), `hello_world` (underscore)

#### `confirmed`
Looks for a field named `{field}_confirmation` in the data:
```php
$data = ['password' => 'secret', 'password_confirmation' => 'secret'];
$rules = ['password' => ['confirmed']]; // Passes
```

---

## Individual Validators

All validators are available as standalone static methods:

```php
Validator::isEmail('user@example.com');      // true
Validator::isNotEmpty('hello');              // true
Validator::minLength('hello', 5);           // true
Validator::maxLength('hi', 10);             // true
Validator::isInt('42');                     // true
Validator::isPositiveInt('0');             // false (must be >= 1)
Validator::isUrl('https://example.com');    // true
Validator::isDate('2024-01-15');           // true
Validator::isBoolean('yes');              // true
Validator::isAlpha('Héllo');              // true (accented chars)
Validator::isAlphaNum('Hello123');         // true
Validator::isSlug('hello-world');          // true
Validator::matches('ABC', '/^[A-Z]+$/');  // true
```

---

## Output Encoding

### `Validator::escape(string $value): string`

Escapes HTML special characters for safe output in views. Equivalent to `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

```php
<?= Validator::escape($userInput) ?>
// <script> becomes &lt;script&gt;
// "quotes" becomes &quot;quotes&quot;
// 'single' becomes &#039;single&#039;
```

---

## Common Validation Patterns

### Registration Form

```php
$errors = Validator::validate($_POST, [
    'first_name' => ['required', 'alpha', 'min:2', 'max:100'],
    'last_name'  => ['required', 'alpha', 'min:2', 'max:100'],
    'email'      => ['required', 'email', 'max:255'],
    'password'   => ['required', 'min:8', 'max:128', 'confirmed'],
    'terms'      => ['required', 'boolean'],
]);
```

### API Input

```php
$errors = Validator::validate($data, [
    'title'      => ['required', 'min:3', 'max:255'],
    'slug'       => ['required', 'slug'],
    'category'   => ['required', 'in:tech,science,art,other'],
    'website'    => ['url'],
    'publish_at' => ['date'],
]);

if (!empty($errors)) {
    ApiResponse::error($errors, 422);
}
```

### Profile Update

```php
$errors = Validator::validate($_POST, [
    'display_name' => ['required', 'alpha_num', 'min:3', 'max:30'],
    'bio'          => ['max:500'],
    'website'      => ['url'],
]);
```
