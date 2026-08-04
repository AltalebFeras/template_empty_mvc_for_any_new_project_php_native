<?php

/**
 * Migration: Create jobs table for the background queue.
 */
return [
    'up' => "
        CREATE TABLE IF NOT EXISTS `jobs` (
            `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `type`         VARCHAR(100) NOT NULL,
            `payload`      JSON NOT NULL,
            `status`       ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `error`        TEXT DEFAULT NULL,
            `run_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME DEFAULT NULL,
            INDEX `idx_jobs_status_run` (`status`, `run_at`),
            INDEX `idx_jobs_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'down' => "DROP TABLE IF EXISTS `jobs`;",
];
