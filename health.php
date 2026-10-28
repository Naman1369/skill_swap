<?php
/**
 * Health check for the hosting platform / uptime monitors.
 * Returns 200 when the app can reach the database and 503 otherwise. Reveals no internals.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    db_connect()->query('SELECT 1 FROM users LIMIT 1');
    echo '{"status":"ok"}';
} catch (Throwable $e) {
    error_log('SkillSwap health check failed: ' . $e->getMessage());
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
