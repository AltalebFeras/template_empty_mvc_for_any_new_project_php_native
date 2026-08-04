<?php

namespace App\Services;

/**
 * Normalized API Response Builder.
 *
 * Enforces a consistent JSON response structure across all API endpoints:
 * {
 *   "status": "success"|"error",
 *   "data": mixed,
 *   "errors": array|null,
 *   "meta": object|null
 * }
 *
 * Usage:
 *   ApiResponse::success(['user' => $user]);
 *   ApiResponse::error(['Invalid email.'], 422);
 *   ApiResponse::paginated($items, $total, $page, $perPage);
 */
final class ApiResponse
{
    /**
     * Sends a success response.
     *
     * @param mixed      $data   Response payload.
     * @param array|null $meta   Optional metadata (pagination, etc.).
     * @param int        $status HTTP status code (default 200).
     */
    public static function success(mixed $data = null, ?array $meta = null, int $status = 200): never
    {
        self::send($status, [
            'status' => 'success',
            'data'   => $data,
            'errors' => null,
            'meta'   => $meta,
        ]);
    }

    /**
     * Sends an error response.
     *
     * @param array<string>|array<string, string> $errors Error messages.
     * @param int   $status HTTP status code (default 400).
     * @param mixed $data   Optional data to include (rare for errors).
     */
    public static function error(array $errors, int $status = 400, mixed $data = null): never
    {
        self::send($status, [
            'status' => 'error',
            'data'   => $data,
            'errors' => $errors,
            'meta'   => null,
        ]);
    }

    /**
     * Sends a paginated success response.
     *
     * @param array $items   The page of results.
     * @param int   $total   Total number of results.
     * @param int   $page    Current page number.
     * @param int   $perPage Items per page.
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): never
    {
        self::success($items, [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    /**
     * Sends a 201 Created response.
     */
    public static function created(mixed $data = null): never
    {
        self::success($data, null, 201);
    }

    /**
     * Sends a 204 No Content response.
     */
    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }

    /**
     * Internal: sends the JSON response and exits.
     */
    private static function send(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
