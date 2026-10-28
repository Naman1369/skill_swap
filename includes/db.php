<?php
/**
 * Database connection (PDO).
 * All queries in the application use prepared statements through this connection.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $offset = (new DateTime('now', new DateTimeZone(APP_TZ)))->format('P');

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 5,
                // Keep NOW() and CURRENT_TIMESTAMP in the same time zone as PHP.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '{$offset}'",
            ]);
        } catch (PDOException $e) {
            error_log('SkillSwap DB connection failed: ' . $e->getMessage());

            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Could not connect to MySQL: {$e->getMessage()}\n");
                exit(1);
            }

            http_response_code(503);
            header('Retry-After: 30');
            $detail = APP_DEBUG ? '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>' : '';
            echo '<!doctype html><meta charset="utf-8"><title>SkillSwap is unavailable</title>'
                . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:0 16px;color:#171a2c">'
                . '<h1 style="font-size:1.4rem">We can\'t reach the database right now</h1>'
                . '<p>Please try again in a minute. If the problem continues, contact the site administrator.</p>'
                . $detail . '</div>';
            exit;
        }
    }

    return $pdo;
}
