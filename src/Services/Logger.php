<?php

namespace App\Services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * PSR-3 Compatible Structured Logger.
 *
 * Wraps Monolog with pre-configured channels, JSON output,
 * PII auto-masking, and context enrichment.
 *
 * Channels: app, security, database, mail
 *
 * Usage:
 *   Logger::channel('app')->info('User logged in', ['user_id' => 42]);
 *   Logger::channel('security')->warning('CSRF violation', ['ip' => '...']);
 */
final class Logger
{
    /** @var array<string, MonologLogger> Channel instances */
    private static array $channels = [];

    /**
     * Returns a Monolog logger instance for the given channel.
     *
     * @param string $channel Channel name (app, security, database, mail).
     */
    public static function channel(string $channel = 'app'): MonologLogger
    {
        if (isset(self::$channels[$channel])) {
            return self::$channels[$channel];
        }

        $logDir = dirname(__DIR__, 2) . '/' . Config::get('LOG_PATH', 'logs');

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/' . $channel . '-' . date('Y-m-d') . '.log';

        $level = self::parseLevel(Config::get('LOG_LEVEL', 'debug'));

        $logger = new MonologLogger($channel);

        // Stream handler with JSON-formatted output.
        $handler = new StreamHandler($logFile, $level);

        // PSR-3 message interpolation.
        $logger->pushProcessor(new PsrLogMessageProcessor());

        // PII masking processor.
        $logger->pushProcessor(function (array $record) {
            if (isset($record['context'])) {
                $record['context'] = self::maskPiiInContext($record['context']);
            }
            return $record;
        });

        // Request context enrichment.
        $logger->pushProcessor(function (array $record) {
            $record['extra']['request_id'] = $_SERVER['HTTP_X_REQUEST_ID'] ?? substr(bin2hex(random_bytes(8)), 0, 16);
            $record['extra']['ip']         = $_SERVER['REMOTE_ADDR'] ?? 'cli';

            if (isset($_SESSION['user_id'])) {
                $record['extra']['user_id'] = $_SESSION['user_id'];
            }

            return $record;
        });

        $logger->pushHandler($handler);

        self::$channels[$channel] = $logger;

        return $logger;
    }

    /**
     * Shorthand: logs an info message to the default 'app' channel.
     */
    public static function info(string $message, array $context = []): void
    {
        self::channel('app')->info($message, $context);
    }

    /**
     * Shorthand: logs an error to the default 'app' channel.
     */
    public static function error(string $message, array $context = []): void
    {
        self::channel('app')->error($message, $context);
    }

    /**
     * Masks PII in log context values.
     */
    private static function maskPiiInContext(array $context): array
    {
        $piiKeys = ['email', 'password', 'token', 'secret', 'credit_card', 'ssn', 'phone'];

        foreach ($context as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            // Mask known PII keys.
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $piiKeys, true)) {
                if ($lowerKey === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $parts = explode('@', $value);
                    $context[$key] = substr($parts[0], 0, 1) . '***@' . $parts[1];
                } elseif (in_array($lowerKey, ['password', 'secret', 'token', 'credit_card', 'ssn'], true)) {
                    $context[$key] = '[REDACTED]';
                }
                continue;
            }

            // Auto-detect and mask email addresses in values.
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $parts = explode('@', $value);
                $context[$key] = substr($parts[0], 0, 1) . '***@' . $parts[1];
            }
        }

        return $context;
    }

    /**
     * Parses a log level string to Monolog level constant.
     */
    private static function parseLevel(string $level): int
    {
        return match (strtolower($level)) {
            'emergency' => MonologLogger::EMERGENCY,
            'alert'     => MonologLogger::ALERT,
            'critical'  => MonologLogger::CRITICAL,
            'error'     => MonologLogger::ERROR,
            'warning'   => MonologLogger::WARNING,
            'notice'    => MonologLogger::NOTICE,
            'info'      => MonologLogger::INFO,
            default     => MonologLogger::DEBUG,
        };
    }
}
