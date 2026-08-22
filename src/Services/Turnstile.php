<?php

namespace App\Services;

use RuntimeException;

/**
 * Cloudflare Turnstile Verification Service.
 *
 * Validates Turnstile response tokens server-side by calling
 * the Cloudflare siteverify API endpoint.
 *
 * Usage:
 *   if (!Turnstile::verify($_POST['cf-turnstile-response'])) {
 *       // reject the request
 *   }
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Maximum age of a Turnstile token (5 minutes). */
    private const MAX_TOKEN_AGE_SECONDS = 300;

    /**
     * Verifies a Turnstile response token server-side.
     *
     * @param string      $responseToken The cf-turnstile-response from the form.
     * @param string|null $remoteIp      Client IP (optional, for extra validation).
     * @return TurnstileResult Structured verification result.
     */
    public static function verify(string $responseToken, ?string $remoteIp = null): TurnstileResult
    {
        $secretKey = Config::get('TURNSTILE_SECRET_KEY', '');

        if ($secretKey === '') {
            // If no key configured, skip verification (development mode).
            if (!Config::isProduction()) {
                return new TurnstileResult(true, []);
            }
            return new TurnstileResult(false, ['missing-secret-key']);
        }

        if ($responseToken === '') {
            return new TurnstileResult(false, ['missing-input-response']);
        }

        $postData = [
            'secret'   => $secretKey,
            'response' => $responseToken,
        ];

        if ($remoteIp !== null) {
            $postData['remoteip'] = $remoteIp;
        }

        $ch = curl_init(self::VERIFY_URL);
        if ($ch === false) {
            return new TurnstileResult(false, ['curl-init-failed']);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => Config::isProduction(),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            try {
                Logger::channel('security')->error('Turnstile API call failed', [
                    'http_code'  => $httpCode,
                    'curl_error' => $curlErr,
                ]);
            } catch (\Throwable) {}
            return new TurnstileResult(false, ['api-call-failed']);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return new TurnstileResult(false, ['invalid-json-response']);
        }

        $success    = $data['success'] ?? false;
        $errorCodes = $data['error-codes'] ?? [];
        $hostname   = $data['hostname'] ?? null;
        $timestamp  = $data['challenge_ts'] ?? null;
        $isTestKey  = $data['metadata']['result_with_testing_key'] ?? false;

        // Validate hostname matches our domain (only in production with real keys).
        if ($success && $hostname !== null && Config::isProduction() && !$isTestKey) {
            $expectedHost = parse_url(Config::baseUrl(), PHP_URL_HOST);
            if ($expectedHost && $hostname !== $expectedHost) {
                $success    = false;
                $errorCodes[] = 'hostname-mismatch';
            }
        }

        // Validate token freshness.
        if ($success && $timestamp !== null && !$isTestKey) {
            $challengeTime = strtotime($timestamp);
            if ($challengeTime !== false && (time() - $challengeTime) > self::MAX_TOKEN_AGE_SECONDS) {
                $success    = false;
                $errorCodes[] = 'token-expired';
            }
        }

        return new TurnstileResult($success, $errorCodes);
    }
}

/**
 * Immutable result of a Turnstile verification.
 */
final class TurnstileResult
{
    /**
     * @param array<int|string, mixed> $errorCodes
     */
    public function __construct(
        public readonly bool  $success,
        public readonly array $errorCodes = [],
    ) {}

    public function failed(): bool
    {
        return !$this->success;
    }
}
