<?php

/**
 * Migration: Create users table.
 */
return [
    'up' => "
        CREATE TABLE IF NOT EXISTS `users` (
            `user_id`      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `first_name`   VARCHAR(100) NOT NULL,
            `last_name`    VARCHAR(100) NOT NULL,
            `email`        VARCHAR(255) NOT NULL UNIQUE,
            `password`     VARCHAR(255) NOT NULL COMMENT 'Argon2id hash',
            `is_activated` TINYINT(1) NOT NULL DEFAULT 0,
            `role_id`      INT UNSIGNED NOT NULL DEFAULT 1,
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_users_email` (`email`),
            INDEX `idx_users_role` (`role_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    'down' => "DROP TABLE IF EXISTS `users`;",
];
