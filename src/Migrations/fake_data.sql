-- ============================================================
-- Seed data for Native PHP MVC Framework Template
-- ============================================================

-- Initial Administrator Account
-- Email: admin@example.com
-- Password: Admin123456! (Argon2id hash)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `is_activated`, `role_id`, `created_at`)
VALUES
('System', 'Admin', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=2$TDJLdHoxVDBCUkhqcERxaw$5Y/L5/W96pKAlOrrUrI1XJwNWcilz9uoyhpjEgUVlIo', 1, 3, NOW())
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role_id` = 3, `is_activated` = 1;
