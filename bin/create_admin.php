<?php

require_once __DIR__ . '/../vendor/autoload.php';

App\Services\Config::boot();

$db    = App\Services\Database::getInstance();
$email = $argv[1] ?? 'admin@example.com';
$pass  = $argv[2] ?? 'Admin123456!';
$hash  = password_hash($pass, PASSWORD_ARGON2ID);

// Check if already exists
$check = $db->prepare('SELECT user_id FROM `users` WHERE `email` = :email');
$check->execute([':email' => $email]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $stmt = $db->prepare(
        'UPDATE `users` SET `password` = :pw, `role_id` = 3, `is_activated` = 1,
         `first_name` = :fn, `last_name` = :ln WHERE `email` = :email'
    );
    $stmt->execute([
        ':pw'    => $hash,
        ':fn'    => 'System',
        ':ln'    => 'Admin',
        ':email' => $email,
    ]);
    echo "✓ Admin user updated (user_id: {$existing['user_id']})" . PHP_EOL;
} else {
    $stmt = $db->prepare(
        'INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `is_activated`, `role_id`)
         VALUES (:fn, :ln, :email, :pw, 1, 3)'
    );
    $stmt->execute([
        ':fn'    => 'System',
        ':ln'    => 'Admin',
        ':email' => $email,
        ':pw'    => $hash,
    ]);
    echo '✓ Admin user created. ID: ' . $db->lastInsertId() . PHP_EOL;
}

echo "  Email    : {$email}" . PHP_EOL;
echo "  Password : {$pass}" . PHP_EOL;
echo "  Role ID  : 3 (admin)" . PHP_EOL;
echo "  Active   : yes" . PHP_EOL;
