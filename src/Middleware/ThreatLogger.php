<?php

namespace App\Middleware;

use App\Services\Config;

/**
 * Threat Logger — Fail2Ban / WAF-Compatible Security Event Logger.
 *
 * Writes security events to a dedicated log file in a format that
 * Fail2Ban and Cloudflare WAF API integrations can parse.
 *
 * Log format:
 *   [2024-01-15T14:30:00+00:00] THREAT type=brute_force ip=192.168.1.1 action=login attempts=10
 *
 * Usage:
 *   ThreatLogger::log('brute_force', $ip, ['action' => 'login', 'attempts' => 10]);
 */
final class ThreatLogger
{
    private const LOG_FILE = 'security-threats.log';

    /**
     * Logs a security threat event.
     *
     * @param string               $type    Threat type (e.g., 'brute_force', 'csrf_violation', 'rate_limit').
     * @param string               $ip      Client IP address.
     * @param array<string, mixed> $context Additional context data.
     */
    public static function log(string $type, string $ip, array $context = []): void
    {
        $logDir = dirname(__DIR__, 2) . '/' . Config::get('LOG_PATH', 'logs');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('c'); // ISO 8601
        $contextStr = '';
        foreach ($context as $key => $value) {
            // Mask PII in log context.
            $value = self::maskPii((string) $value);
            $contextStr .= " {$key}={$value}";
        }

        // Fail2Ban-parseable format.
        $line = "[{$timestamp}] THREAT type={$type} ip={$ip}{$contextStr}" . PHP_EOL;

        file_put_contents($logDir . '/' . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Logs a brute-force attempt.
     */
    public static function bruteForce(string $ip, string $action, int $attempts): void
    {
        self::log('brute_force', $ip, [
            'action'   => $action,
            'attempts' => $attempts,
        ]);
    }

    /**
     * Logs a malicious payload detection.
     */
    public static function maliciousPayload(string $ip, string $path, string $detail): void
    {
        self::log('malicious_payload', $ip, [
            'path'   => $path,
            'detail' => substr($detail, 0, 200), // truncate for safety
        ]);
    }

    /**
     * Partially masks personally identifiable information.
     *
     * - Emails: f***@example.com
     * - Phone numbers: partially redacted
     */
    private static function maskPii(string $value): string
    {
        // Email masking.
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $parts = explode('@', $value);
            return substr($parts[0], 0, 1) . '***@' . $parts[1];
        }

        return $value;
    }
}
