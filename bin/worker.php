#!/usr/bin/env php
<?php

/**
 * Job Queue Worker CLI.
 *
 * Processes pending jobs from the queue.
 *
 * Usage:
 *   php bin/worker.php              — Run indefinitely
 *   php bin/worker.php --max=100    — Process up to 100 jobs then exit
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\Config;
use App\Services\JobQueue;

Config::boot();

$maxJobs = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--max=')) {
        $maxJobs = (int) substr($arg, 6);
    }
}

echo "Worker started. Listening for jobs...\n";

JobQueue::work($maxJobs);
