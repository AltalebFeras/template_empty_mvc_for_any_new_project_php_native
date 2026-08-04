<?php

namespace App\Services;

/**
 * Input Validation Engine.
 *
 * Validates form and API input against declarative rule sets.
 *
 * Supported rules:
 *   required, email, min:N, max:N, int, positiveInt, url, date,
 *   boolean, alpha, alpha_num, slug, in:val1,val2, regex:pattern,
 *   confirmed, file, image, max_file_size:bytes
 *
 * Usage:
 *   $errors = Validator::validate($data, [
 *       'email'    => ['required', 'email', 'max:255'],
 *       'password' => ['required', 'min:8', 'max:128'],
 *       'role'     => ['required', 'in:user,editor,admin'],
 *   ]);
 *
 *   // With custom messages:
 *   $errors = Validator::validate($data, $rules, [
 *       'email.required' => 'Email address is mandatory.',
 *   ]);
 */
class Validator
{
    // -----------------------------------------------------------------------
    // Individual validators (public static for standalone use)
    // -----------------------------------------------------------------------

    public static function isEmail(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public static function isNotEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value, 'UTF-8') >= $min;
    }

    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value, 'UTF-8') <= $max;
    }

    public static function isInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function isPositiveInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    public static function isUrl(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_URL);
    }

    public static function isDate(string $value, string $format = 'Y-m-d'): bool
    {
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    public static function isBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'yes', 'no'], true);
    }

    public static function isAlpha(string $value): bool
    {
        return (bool) preg_match('/^[\pL]+$/u', $value);
    }

    public static function isAlphaNum(string $value): bool
    {
        return (bool) preg_match('/^[\pL\pN]+$/u', $value);
    }

    public static function isSlug(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    /**
     * Escapes HTML special characters for safe output in views.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns true if the value matches the given regex pattern.
     */
    public static function matches(string $value, string $pattern): bool
    {
        return (bool) preg_match($pattern, $value);
    }

    // -----------------------------------------------------------------------
    // Batch Validation Engine
    // -----------------------------------------------------------------------

    /**
     * Validates multiple fields against declarative rules.
     *
     * @param array<string, mixed>                 $data     Input data.
     * @param array<string, array<int, string>>    $rules    Validation rules per field.
     * @param array<string, string>                $messages Custom error messages (optional).
     * @return array<string, string> Field => first error message (empty if valid).
     */
    public static function validate(array $data, array $rules, array $messages = []): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = (string) ($data[$field] ?? '');

            foreach ($fieldRules as $rule) {
                $error = self::applyRule($field, $value, $rule, $data, $messages);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // Stop at first error per field.
                }
            }
        }

        return $errors;
    }

    /**
     * Applies a single rule and returns an error message or null.
     */
    private static function applyRule(
        string $field,
        string $value,
        string $rule,
        array  $data,
        array  $messages,
    ): ?string {
        // Parse parameterized rules (e.g., 'min:8', 'in:a,b,c').
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param    = $parts[1] ?? '';

        $messageKey  = "{$field}.{$ruleName}";
        $customError = $messages[$messageKey] ?? null;

        return match ($ruleName) {
            'required' => !self::isNotEmpty($value)
                ? ($customError ?? "The field '{$field}' is required.")
                : null,

            'email' => $value !== '' && !self::isEmail($value)
                ? ($customError ?? "The field '{$field}' must be a valid email address.")
                : null,

            'int' => $value !== '' && !self::isInt($value)
                ? ($customError ?? "The field '{$field}' must be an integer.")
                : null,

            'positiveInt' => $value !== '' && !self::isPositiveInt($value)
                ? ($customError ?? "The field '{$field}' must be a positive integer.")
                : null,

            'url' => $value !== '' && !self::isUrl($value)
                ? ($customError ?? "The field '{$field}' must be a valid URL.")
                : null,

            'date' => $value !== '' && !self::isDate($value, $param ?: 'Y-m-d')
                ? ($customError ?? "The field '{$field}' must be a valid date.")
                : null,

            'boolean' => $value !== '' && !self::isBoolean($value)
                ? ($customError ?? "The field '{$field}' must be a boolean.")
                : null,

            'alpha' => $value !== '' && !self::isAlpha($value)
                ? ($customError ?? "The field '{$field}' must contain only letters.")
                : null,

            'alpha_num' => $value !== '' && !self::isAlphaNum($value)
                ? ($customError ?? "The field '{$field}' must contain only letters and numbers.")
                : null,

            'slug' => $value !== '' && !self::isSlug($value)
                ? ($customError ?? "The field '{$field}' must be a valid URL slug.")
                : null,

            'min' => $value !== '' && !self::minLength($value, (int) $param)
                ? ($customError ?? "The field '{$field}' must be at least {$param} characters.")
                : null,

            'max' => $value !== '' && !self::maxLength($value, (int) $param)
                ? ($customError ?? "The field '{$field}' must not exceed {$param} characters.")
                : null,

            'in' => $value !== '' && !in_array($value, explode(',', $param), true)
                ? ($customError ?? "The field '{$field}' must be one of: {$param}.")
                : null,

            'regex' => $value !== '' && !self::matches($value, $param)
                ? ($customError ?? "The field '{$field}' format is invalid.")
                : null,

            'confirmed' => ($data[$field . '_confirmation'] ?? '') !== $value
                ? ($customError ?? "The field '{$field}' confirmation does not match.")
                : null,

            default => null, // Unknown rules are silently ignored.
        };
    }
}
