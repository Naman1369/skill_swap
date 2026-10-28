<?php
/**
 * Health check for the hosting platform / uptime monitors.
 * Returns 200 when the app can reach the database and 503 otherwise. Reveals no internals.
 */
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $pdo->query('SELECT 1 FROM users LIMIT 1');
    echo '{"status":"ok"}';
} catch (Throwable $e) {
    error_log('SkillSwap health check failed: ' . $e->getMessage());
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
