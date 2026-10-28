<?php
/**
 * Creates or upgrades the database schema. Safe to run on every start-up:
 *   php database/install.php
 *
 * - Creates any missing tables (database/schema.sql).
 * - Upgrades databases created before roles existed (adds users.role and users.status).
 * Reads its connection settings from includes/config.php (environment variables).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$pdo = db();

$sql = file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
$statements = array_filter(array_map('trim', explode(';', $sql)), fn ($s) => $s !== '');

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

// Upgrade path for databases created by an earlier version of the schema.
$hasColumn = function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
};

$upgraded = [];
if (!$hasColumn('users', 'role')) {
    $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('student','admin') NOT NULL DEFAULT 'student' AFTER password_hash, ADD KEY idx_users_role (role)");
    $upgraded[] = 'users.role';
}
if (!$hasColumn('users', 'status')) {
    $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER role");
    $upgraded[] = 'users.status';
}

$tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
echo 'Database ready (' . $tables . ' tables)' . ($upgraded ? '; upgraded: ' . implode(', ', $upgraded) : '') . ".\n";
