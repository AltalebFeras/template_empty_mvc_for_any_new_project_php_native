<?php

namespace App\Services;

/**
 * Circuit Breaker Pattern for External API Calls.
 *
 * Prevents cascading failures when third-party services (mailer, payment
 * gateways, webhooks) are down by temporarily stopping calls.
 *
 * States:
 *   CLOSED    → Normal operation, requests pass through.
 *   OPEN      → Service is down, requests fail immediately.
 *   HALF_OPEN → Testing if service has recovered (limited requests).
 *
 * Usage:
 *   $cb = new CircuitBreaker('payment_gateway');
 *   $result = $cb->call(function() {
 *       return PaymentApi::charge($amount);
 *   });
 */
final class CircuitBreaker
{
    private const STATE_CLOSED    = 'closed';
    private const STATE_OPEN      = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $service;
    private int    $failureThreshold;
    private int    $recoveryTimeout; // seconds before trying again
    private int    $halfOpenMaxAttempts;

    public function __construct(
        string $service,
        int    $failureThreshold = 5,
        int    $recoveryTimeout = 60,
        int    $halfOpenMaxAttempts = 2,
    ) {
        $this->service             = $service;
        $this->failureThreshold    = $failureThreshold;
        $this->recoveryTimeout     = $recoveryTimeout;
        $this->halfOpenMaxAttempts = $halfOpenMaxAttempts;
    }

    /**
     * Executes the given callable through the circuit breaker.
     *
     * @param callable $operation The operation to execute.
     * @param mixed    $fallback  Value to return when the circuit is open.
     * @return mixed The result of the operation or the fallback.
     * @throws \RuntimeException If the circuit is open and no fallback given.
     */
    public function call(callable $operation, mixed $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            Logger::channel('app')->warning('Circuit breaker OPEN — skipping call', [
                'service' => $this->service,
            ]);

            if ($fallback !== null) {
                return is_callable($fallback) ? $fallback() : $fallback;
            }

            throw new \RuntimeException("Circuit breaker is OPEN for service: {$this->service}");
        }

        try {
            $result = $operation();

            // Success — reset failure count (transition to CLOSED if HALF_OPEN).
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            Logger::channel('app')->error('Circuit breaker recorded failure', [
                'service' => $this->service,
                'error'   => $e->getMessage(),
                'state'   => $this->getState(),
            ]);

            if ($fallback !== null) {
                return is_callable($fallback) ? $fallback() : $fallback;
            }

            throw $e;
        }
    }

    /**
     * Returns the current state of the circuit breaker.
     */
    public function getState(): string
    {
        $data = $this->loadState();

        if ($data['state'] === self::STATE_OPEN) {
            // Check if recovery timeout has elapsed → transition to HALF_OPEN.
            if (time() - $data['last_failure_at'] >= $this->recoveryTimeout) {
                return self::STATE_HALF_OPEN;
            }
            return self::STATE_OPEN;
        }

        return $data['state'];
    }

    private function recordSuccess(): void
    {
        $this->saveState([
            'state'           => self::STATE_CLOSED,
            'failure_count'   => 0,
            'last_failure_at' => 0,
            'half_open_attempts' => 0,
        ]);
    }

    private function recordFailure(): void
    {
        $data = $this->loadState();
        $data['failure_count']++;
        $data['last_failure_at'] = time();

        if ($data['state'] === self::STATE_HALF_OPEN) {
            $data['half_open_attempts']++;
            if ($data['half_open_attempts'] >= $this->halfOpenMaxAttempts) {
                $data['state'] = self::STATE_OPEN;
            }
        } elseif ($data['failure_count'] >= $this->failureThreshold) {
            $data['state'] = self::STATE_OPEN;
        }

        $this->saveState($data);
    }

    // -----------------------------------------------------------------------
    // Persistence (file-based, could be Redis)
    // -----------------------------------------------------------------------

    private function statePath(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache/circuit_breaker';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . md5($this->service) . '.json';
    }

    private function loadState(): array
    {
        $path = $this->statePath();
        if (!file_exists($path)) {
            return [
                'state'              => self::STATE_CLOSED,
                'failure_count'      => 0,
                'last_failure_at'    => 0,
                'half_open_attempts' => 0,
            ];
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [
            'state'              => self::STATE_CLOSED,
            'failure_count'      => 0,
            'last_failure_at'    => 0,
            'half_open_attempts' => 0,
        ];
    }

    private function saveState(array $data): void
    {
        file_put_contents($this->statePath(), json_encode($data), LOCK_EX);
    }
}
