# Background Jobs & Worker Queue

Covers asynchronous job dispatching, SQL queue storage, atomic job claiming, exponential backoff retries, and CLI worker execution.

**Source files:** `src/Services/JobQueue.php`, `bin/worker.php`, `src/Migrations/2024_003_create_jobs_table.php`

---

## Overview

The `JobQueue` service decouples time-heavy tasks (sending email, processing images, invoking third-party webhooks) from the HTTP request-response cycle.

```
HTTP Request → JobQueue::dispatch('send_email', $payload) → DB (`jobs` table)
                                                                  │
CLI Worker Process (bin/worker.php) ◀── FOR UPDATE SKIP LOCKED ──┘
    │
    ▼
Executes job handler → Marks completed / failed
```

---

## Dispatching Jobs

### `JobQueue::dispatch(string $type, array $payload = [], int $delay = 0): void`

Enqueues a job into the database.

```php
use App\Services\JobQueue;

// Immediate job
JobQueue::dispatch('send_email', [
    'to'      => 'user@example.com',
    'subject' => 'Welcome to our platform!',
    'body'    => 'Thank you for registering.',
]);

// Delayed job (runs after 300 seconds / 5 minutes)
JobQueue::dispatch('send_reminder', [
    'user_id' => 42,
], delay: 300);
```

---

## Database Schema (`jobs` Table)

| Column | Type | Description |
|--------|------|-------------|
| `id` | `BIGINT UNSIGNED` | Primary key |
| `type` | `VARCHAR(100)` | Job type identifier (e.g. `send_email`) |
| `payload` | `JSON` | Encoded job data array |
| `status` | `ENUM` | `pending`, `processing`, `completed`, `failed` |
| `attempts` | `TINYINT` | Number of execution attempts |
| `error` | `TEXT` | Exception error message on failure |
| `run_at` | `DATETIME` | Scheduled execution time |
| `created_at` | `DATETIME` | Dispatch timestamp |
| `completed_at` | `DATETIME` | Completion timestamp |

---

## Worker Execution

The worker script claims and processes pending jobs continuously.

### Running via CLI

```bash
# Run worker indefinitely
php bin/worker.php

# Process max 100 jobs then exit (useful for cron / supervisor recycling)
php bin/worker.php --max=100
```

### Atomic Job Claiming

To support multiple concurrent worker processes safely without race conditions, jobs are claimed using **pessimistic locking**:

```sql
SELECT * FROM `jobs`
WHERE `status` = 'pending'
  AND `run_at` <= NOW()
  AND `attempts` < 3
ORDER BY `run_at` ASC
LIMIT 1
FOR UPDATE SKIP LOCKED
```

`FOR UPDATE SKIP LOCKED` ensures concurrent workers instantly skip rows locked by other workers.

---

## Retry Strategy & Exponential Backoff

If a job throws an uncaught exception:

1. Attempt count is incremented: `$attempts = $job['attempts'] + 1`.
2. If `$attempts < 3`: status remains `pending`, but `run_at` is pushed into the future using exponential backoff:
   $$\text{retry\_delay} = 2^{\text{attempts}} \times 30\text{ seconds}$$
   - Attempt 1 failure $\rightarrow$ retry in 60s
   - Attempt 2 failure $\rightarrow$ retry in 120s
   - Attempt 3 failure $\rightarrow$ marked `failed`, no further retries
3. If max attempts (3) reached: status set to `failed` and error message saved to `error` column.

---

## Registering Custom Job Handlers

To add a new background job type:

1. Open `src/Services/JobQueue.php`.
2. Add a new match arm in `execute()`:

```php
private static function execute(string $type, array $payload): void
{
    match ($type) {
        'send_email'      => self::handleSendEmail($payload),
        'process_image'   => self::handleProcessImage($payload), // Custom handler
        'generate_report' => self::handleGenerateReport($payload), // Custom handler
        default => throw new \RuntimeException("Unknown job type: {$type}"),
    };
}
```

3. Implement the handler method:

```php
private static function handleProcessImage(array $payload): void
{
    $imagePath = $payload['path'] ?? '';
    ImageProcessor::sanitize($imagePath);
    ImageProcessor::thumbnail($imagePath, 300, 300);
}
```

---

## Production Supervisor Setup

Keep the worker running continuously using **Supervisor**:

```ini
; /etc/supervisor/conf.d/php-worker.conf
[program:php-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bin/worker.php --max=500
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/logs/worker.log
```
