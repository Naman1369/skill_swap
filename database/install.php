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

if (!$hasColumn('profiles', 'avatar_data')) {
    $pdo->exec('ALTER TABLE profiles ADD COLUMN avatar_mime VARCHAR(20) NULL AFTER avatar, ADD COLUMN avatar_data MEDIUMBLOB NULL AFTER avatar_mime');
    $upgraded[] = 'profiles.avatar_data';
}
$hasIndex = $pdo->prepare(
    'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
);
$hasIndex->execute(['profiles', 'idx_profiles_avatar']);
if (!$hasIndex->fetchColumn()) {
    $pdo->exec('ALTER TABLE profiles ADD KEY idx_profiles_avatar (avatar)');
    $upgraded[] = 'profiles.idx_profiles_avatar';
}

// Photos saved as files by older versions are moved into the database (or dropped if the file is gone).
$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$legacy = $pdo->query('SELECT user_id, avatar FROM profiles WHERE avatar IS NOT NULL AND avatar_data IS NULL')->fetchAll();
$imported = 0;
foreach ($legacy as $row) {
    $path = AVATAR_DIR . basename($row['avatar']);
    $ext = strtolower(pathinfo($row['avatar'], PATHINFO_EXTENSION));
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $row['avatar']) && is_file($path) && filesize($path) <= 2 * 1024 * 1024) {
        $pdo->prepare('UPDATE profiles SET avatar_mime = ?, avatar_data = ? WHERE user_id = ?')
            ->execute([$types[$ext], file_get_contents($path), $row['user_id']]);
        $imported++;
    } else {
        $pdo->prepare('UPDATE profiles SET avatar = NULL WHERE user_id = ?')->execute([$row['user_id']]);
    }
}
if ($legacy) {
    $upgraded[] = "photos moved to database: {$imported}/" . count($legacy);
}

$tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();
echo 'Database ready (' . $tables . ' tables)' . ($upgraded ? '; upgraded: ' . implode(', ', $upgraded) : '') . ".\n";
