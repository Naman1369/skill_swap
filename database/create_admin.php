<?php
/**
 * Creates the first administrator, or promotes an existing account to admin.
 *
 *   php database/create_admin.php admin@example.com "Admin Name"      (asks for the password)
 *   ADMIN_EMAIL=... ADMIN_PASSWORD=... [ADMIN_NAME=...] php database/create_admin.php
 *
 * If the account already exists it is made an active admin and its password is left unchanged.
 * Used automatically at start-up when ADMIN_EMAIL and ADMIN_PASSWORD are set in the environment.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$email = mb_strtolower(trim($argv[1] ?? (string) env('ADMIN_EMAIL', '')));
$name = trim($argv[2] ?? (string) env('ADMIN_NAME', 'Administrator'));
$password = (string) env('ADMIN_PASSWORD', '');

if ($email === '') {
    fwrite(STDERR, "Usage: php database/create_admin.php <email> [name]   (password from ADMIN_PASSWORD or prompt)\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "'{$email}' is not a valid email address.\n");
    exit(1);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE id = ?")->execute([$existing['id']]);
    echo "Existing account {$email} is now an active admin" . ($existing['role'] === 'admin' ? ' (no change).' : '.') . "\n";
    exit(0);
}

if ($password === '') {
    fwrite(STDOUT, 'Password for the new admin (min 12 characters): ');
    $password = trim((string) fgets(STDIN));
}
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "The admin password must be between 12 and 72 characters.\n");
    exit(1);
}

$pdo->beginTransaction();
$pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
    ->execute([$name !== '' ? $name : 'Administrator', $email, password_hash($password, PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO profiles (user_id) VALUES (?)')->execute([(int) $pdo->lastInsertId()]);
$pdo->commit();

echo "Admin account created for {$email}.\n";
