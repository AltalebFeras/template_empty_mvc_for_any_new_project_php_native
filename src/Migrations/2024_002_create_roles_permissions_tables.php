<?php

/**
 * Migration: Create roles and permissions tables.
 */
return [
    'up' => "
        CREATE TABLE IF NOT EXISTS `roles` (
            `role_id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`        VARCHAR(50) NOT NULL UNIQUE,
            `description` VARCHAR(255) DEFAULT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        INSERT INTO `roles` (`role_id`, `name`, `description`) VALUES
            (1, 'user', 'Standard authenticated user'),
            (2, 'editor', 'Content editor with extended privileges'),
            (3, 'admin', 'Full system administrator');

        CREATE TABLE IF NOT EXISTS `permissions` (
            `permission_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name`          VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g. users.read, posts.write',
            `description`   VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `role_permissions` (
            `role_id`       INT UNSIGNED NOT NULL,
            `permission_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `permission_id`),
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE CASCADE,
            FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`permission_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        ALTER TABLE `users` ADD CONSTRAINT `fk_users_role`
            FOREIGN KEY (`role_id`) REFERENCES `roles`(`role_id`) ON DELETE RESTRICT;
    ",
    'down' => "
        ALTER TABLE `users` DROP FOREIGN KEY `fk_users_role`;
        DROP TABLE IF EXISTS `role_permissions`;
        DROP TABLE IF EXISTS `permissions`;
        DROP TABLE IF EXISTS `roles`;
    ",
];
