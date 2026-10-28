<?php
/**
 * Database connection (PDO).
 * All queries in the application use prepared statements through this connection.
 */

/** Open a new connection. Throws PDOException when the database cannot be reached. */
function db_connect(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $offset = (new DateTime('now', new DateTimeZone(APP_TZ)))->format('P');

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
        // Keep NOW() and CURRENT_TIMESTAMP in the same time zone as PHP.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '{$offset}'",
    ];

    if (DB_SSL) {
        if (DB_SSL_CA_PEM) {
            // Verify the server against the provider's CA certificate.
            $caFile = sys_get_temp_dir() . '/skillswap-db-ca-' . substr(sha1(DB_SSL_CA_PEM), 0, 12) . '.pem';
            if (!is_file($caFile)) {
                file_put_contents($caFile, str_replace('\n', "\n", DB_SSL_CA_PEM));
            }
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        } else {
            // Encrypted, but the server certificate is not checked (no CA available).
            $options[PDO::MYSQL_ATTR_SSL_CIPHER] = 'DEFAULT';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/** The shared connection for this request. Shows a friendly 503 page if the database is down. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = db_connect();
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
