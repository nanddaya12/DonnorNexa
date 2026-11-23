<?php
declare(strict_types=1);

/**
 * CLI Tool: Create or update a system administrator account.
 * Usage: php cli/create_admin.php <email> <password> [name]
 * Example: php cli/create_admin.php admin@myhospital.org MySecurePassword123 "Head of Blood Operations"
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use DonorNexa\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Forbidden: CLI access only.\n";
    exit(1);
}

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$name = $argv[3] ?? 'System Administrator';

if (!$email || !$password) {
    echo "========================================================\n";
    echo "DonorNexa Production Administrator Setup Tool\n";
    echo "========================================================\n";
    echo "Usage: php cli/create_admin.php <email> <password> [name]\n\n";
    echo "Example:\n";
    echo "  php cli/create_admin.php admin@donornexa.pk Admin@123 \"System Administrator\"\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Error: '{$email}' is not a valid email address.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Error: Password must be at least 8 characters long.\n";
    exit(1);
}

$db = Database::connection();
$stmt = $db->prepare("SELECT id, name, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

$hash = password_hash($password, PASSWORD_DEFAULT);

if ($existing) {
    $update = $db->prepare("UPDATE users SET password_hash = ?, role = 'admin', status = 'active', name = ? WHERE id = ?");
    $update->execute([$hash, $name, $existing['id']]);
    echo "SUCCESS: Existing user '{$email}' (ID #{$existing['id']}) updated to active Administrator.\n";
    echo "New password successfully set.\n";
} else {
    $insert = $db->prepare("INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, 'admin', 'active', NOW())");
    $insert->execute([$name, $email, $hash]);
    $newId = $db->lastInsertId();
    echo "SUCCESS: Administrator account '{$email}' created successfully (ID #{$newId}).\n";
}

echo "You can now sign in at your portal's login page.\n";
