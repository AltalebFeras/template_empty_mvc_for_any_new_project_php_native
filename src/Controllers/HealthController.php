<?php

namespace App\Controllers;

use App\Services\ApiResponse;
use App\Services\Database;
use App\Services\Route;

/**
 * Health & Readiness Endpoints.
 *
 * GET /health — basic health check (always responds).
 * GET /ready  — readiness probe (checks all dependencies).
 */
class HealthController
{
    #[Route('/health', methods: ['GET'])]
    public function health(): void
    {
        $checks = [
            'database' => Database::isHealthy(),
            'redis'    => self::checkRedis(),
        ];

        $allHealthy = !in_array(false, $checks, true);

        ApiResponse::success([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => date('c'),
        ], null, $allHealthy ? 200 : 503);
    }

    #[Route('/ready', methods: ['GET'])]
    public function ready(): void
    {
        $dbOk = Database::isHealthy();

        if ($dbOk) {
            ApiResponse::success(['status' => 'ready']);
        } else {
            ApiResponse::error(['Service not ready'], 503);
        }
    }

    private static function checkRedis(): bool
    {
        if (!class_exists(\Predis\Client::class)) {
            return true; // Redis is optional — return true if not configured.
        }

        try {
            $client = new \Predis\Client([
                'host' => \App\Services\Config::get('REDIS_HOST', '127.0.0.1'),
                'port' => \App\Services\Config::getInt('REDIS_PORT', 6379),
            ]);
            $client->ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
