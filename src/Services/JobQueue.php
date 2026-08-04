<?php

namespace App\Services;

/**
 * Lightweight Job Queue — SQL-backed (Redis optional).
 *
 * Decouples time-heavy operations (email sending, webhooks, media processing)
 * from the request-response cycle into a background worker.
 *
 * Usage:
 *   // Enqueue a job:
 *   JobQueue::dispatch('send_email', ['to' => 'user@example.com', 'subject' => '...']);
 *
 *   // Process jobs (run via CLI: php bin/worker.php):
 *   JobQueue::work();
 */
final class JobQueue
{
    private const TABLE = 'jobs';
    private const MAX_ATTEMPTS = 3;

    /**
     * Dispatches a job to the queue.
     *
     * @param string               $type    Job type/handler name.
     * @param array<string, mixed> $payload Job payload data.
     * @param int                  $delay   Delay in seconds before the job becomes available.
     */
    public static function dispatch(string $type, array $payload = [], int $delay = 0): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "INSERT INTO `" . self::TABLE . "` (`type`, `payload`, `status`, `attempts`, `run_at`, `created_at`)
             VALUES (:type, :payload, 'pending', 0, :run_at, NOW())"
        );

        $stmt->execute([
            ':type'    => $type,
            ':payload' => json_encode($payload),
            ':run_at'  => date('Y-m-d H:i:s', time() + $delay),
        ]);
    }

    /**
     * Processes pending jobs (worker loop).
     *
     * Call from a CLI script (bin/worker.php). Runs until manually stopped
     * or max iterations reached.
     *
     * @param int $maxJobs Maximum jobs to process (0 = unlimited).
     * @param int $sleepSeconds Seconds to sleep when no jobs are available.
     */
    public static function work(int $maxJobs = 0, int $sleepSeconds = 5): void
    {
        $processed = 0;
        $db = Database::getInstance();

        while (true) {
            // Claim the next available job atomically.
            $db->beginTransaction();

            try {
                $stmt = $db->prepare(
                    "SELECT * FROM `" . self::TABLE . "`
                     WHERE `status` = 'pending'
                       AND `run_at` <= NOW()
                       AND `attempts` < :max_attempts
                     ORDER BY `run_at` ASC
                     LIMIT 1
                     FOR UPDATE SKIP LOCKED"
                );
                $stmt->execute([':max_attempts' => self::MAX_ATTEMPTS]);
                $job = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$job) {
                    $db->rollBack();
                    sleep($sleepSeconds);
                    continue;
                }

                // Mark as processing.
                $update = $db->prepare(
                    "UPDATE `" . self::TABLE . "` SET `status` = 'processing', `attempts` = `attempts` + 1 WHERE `id` = :id"
                );
                $update->execute([':id' => $job['id']]);
                $db->commit();

            } catch (\Throwable $e) {
                $db->rollBack();
                Logger::channel('app')->error('Job claim failed', ['error' => $e->getMessage()]);
                sleep($sleepSeconds);
                continue;
            }

            // Execute the job.
            try {
                $payload = json_decode($job['payload'], true) ?? [];
                self::execute($job['type'], $payload);

                // Mark as completed.
                $done = $db->prepare(
                    "UPDATE `" . self::TABLE . "` SET `status` = 'completed', `completed_at` = NOW() WHERE `id` = :id"
                );
                $done->execute([':id' => $job['id']]);

                Logger::channel('app')->info('Job completed', [
                    'job_id' => $job['id'],
                    'type'   => $job['type'],
                ]);

            } catch (\Throwable $e) {
                // Mark as failed (will retry if attempts < MAX_ATTEMPTS).
                $attempts = (int) $job['attempts'] + 1;
                $status   = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';

                // Exponential backoff for retries.
                $retryDelay = (int) pow(2, $attempts) * 30; // 60s, 120s, 240s...
                $runAt = date('Y-m-d H:i:s', time() + $retryDelay);

                $fail = $db->prepare(
                    "UPDATE `" . self::TABLE . "` SET `status` = :status, `error` = :error, `run_at` = :run_at WHERE `id` = :id"
                );
                $fail->execute([
                    ':id'     => $job['id'],
                    ':status' => $status,
                    ':error'  => substr($e->getMessage(), 0, 1000),
                    ':run_at' => $runAt,
                ]);

                Logger::channel('app')->error('Job failed', [
                    'job_id'   => $job['id'],
                    'type'     => $job['type'],
                    'attempt'  => $attempts,
                    'error'    => $e->getMessage(),
                ]);
            }

            $processed++;
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
    }

    /**
     * Executes a job by type. Register your job handlers here.
     *
     * @param string $type    Job type name.
     * @param array  $payload Job payload.
     */
    private static function execute(string $type, array $payload): void
    {
        match ($type) {
            'send_email' => self::handleSendEmail($payload),
            // Add more job handlers here:
            // 'process_image' => self::handleProcessImage($payload),
            // 'send_webhook'  => self::handleSendWebhook($payload),
            default => throw new \RuntimeException("Unknown job type: {$type}"),
        };
    }

    /**
     * Example job handler: send email.
     */
    private static function handleSendEmail(array $payload): void
    {
        $mail = new Mail();
        $mail->sendEmail(
            Config::get('MAIL_FROM_ADDRESS', ''),
            Config::get('MAIL_FROM_NAME', 'App'),
            $payload['to'] ?? '',
            $payload['to_name'] ?? '',
            $payload['subject'] ?? '',
            $payload['body'] ?? '',
        );
    }
}
