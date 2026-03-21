<?php

namespace src\Services;

class Validator
{
    /**
     * Returns true if the string is a valid email address.
     */
    public static function isEmail(string $value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Returns true if the value is not empty (trims whitespace before checking).
     */
    public static function isNotEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    /**
     * Returns true if the string length is >= $min characters.
     */
    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value, 'UTF-8') >= $min;
    }

    /**
     * Returns true if the string length is <= $max characters.
     */
    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value, 'UTF-8') <= $max;
    }

    /**
     * Returns true if the value is a valid integer (or numeric string representing one).
     */
    public static function isInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Returns true if the value is a positive integer (>= 1).
     * Useful for validating entity IDs from URL parameters.
     */
    public static function isPositiveInt(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    /**
     * Escapes HTML special characters for safe output in views.
     * Use this before echoing any user-supplied data in HTML.
     *
     * @param string $value Raw user input.
     * @return string HTML-safe string.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns true if the value matches the given regex pattern.
     *
     * @param string $value   The value to test.
     * @param string $pattern A valid PCRE pattern (e.g. '/^[a-z]+$/i').
     */
    public static function matches(string $value, string $pattern): bool
    {
        return (bool) preg_match($pattern, $value);
    }

    /**
     * Validates multiple fields at once and returns an array of error messages.
     *
     * Rules format:
     *   ['field' => ['required', 'email', 'min:6', 'max:100', 'int', 'positiveInt']]
     *
     * @param array<string, mixed>             $data  Associative array of field => value.
     * @param array<string, array<int,string>> $rules Associative array of field => rules.
     * @return array<string, string> Associative array of field => first error message (empty if valid).
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = (string) ($data[$field] ?? '');

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && !self::isNotEmpty($value)) {
                    $errors[$field] = "The field '{$field}' is required.";
                    break;
                }
                if ($rule === 'email' && !self::isEmail($value)) {
                    $errors[$field] = "The field '{$field}' must be a valid email address.";
                    break;
                }
                if ($rule === 'int' && !self::isInt($value)) {
                    $errors[$field] = "The field '{$field}' must be an integer.";
                    break;
                }
                if ($rule === 'positiveInt' && !self::isPositiveInt($value)) {
                    $errors[$field] = "The field '{$field}' must be a positive integer.";
                    break;
                }
                if (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (!self::minLength($value, $min)) {
                        $errors[$field] = "The field '{$field}' must be at least {$min} characters.";
                        break;
                    }
                }
                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (!self::maxLength($value, $max)) {
                        $errors[$field] = "The field '{$field}' must not exceed {$max} characters.";
                        break;
                    }
                }
            }
        }

        return $errors;
    }
}

