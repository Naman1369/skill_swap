<?php
/**
 * Application configuration.
 *
 * Every setting can be overridden with an environment variable, so the same code runs on a
 * local XAMPP install (defaults below) and in a container on a hosting platform.
 */

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function env_int(string $key, int $default): int
{
    $value = env($key);
    return ($value !== null && ctype_digit($value)) ? (int) $value : $default;
}

// "production" turns on secure cookies, HSTS and hides error details.
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', APP_ENV !== 'production' && env('APP_DEBUG', '0') === '1');
define('APP_NAME', 'SkillSwap');

// PHP and the database session must agree on the time zone ("5 hours ago" depends on it).
define('APP_TZ', env('APP_TZ', 'Asia/Kolkata'));
date_default_timezone_set(APP_TZ);

/* ---------- Database ---------- */
// Priority: a single connection URL (DATABASE_URL / MYSQL_URL), then DB_*, then Railway's MYSQL* variables,
// then XAMPP defaults (user "root", empty password).
$dbUrl = env('DATABASE_URL') ?? env('MYSQL_URL');
$db = ['host' => null, 'port' => null, 'name' => null, 'user' => null, 'pass' => null];
if ($dbUrl !== null && ($parts = parse_url($dbUrl)) && !empty($parts['host'])) {
    $db = [
        'host' => $parts['host'],
        'port' => $parts['port'] ?? null,
        'name' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
        'user' => isset($parts['user']) ? rawurldecode($parts['user']) : null,
        'pass' => isset($parts['pass']) ? rawurldecode($parts['pass']) : null,
    ];
}
// Hosted MySQL services (Aiven, TiDB, PlanetScale...) require an encrypted connection. Enable it with DB_SSL=1,
// or let a "?ssl-mode=REQUIRED" in the connection URL turn it on. Give DB_SSL_CA_PEM (the provider's CA
// certificate text) to also verify the server's identity; without it the connection is encrypted but not verified.
$sslFromUrl = isset($parts['query']) && preg_match('/(^|&)ssl-mode=(REQUIRED|VERIFY_CA|VERIFY_IDENTITY)/i', $parts['query']);
define('DB_SSL', env('DB_SSL', '0') === '1' || $sslFromUrl);
define('DB_SSL_CA_PEM', env('DB_SSL_CA_PEM'));
define('DB_HOST', $db['host'] ?? env('DB_HOST') ?? env('MYSQLHOST', '127.0.0.1'));
define('DB_PORT', (int) ($db['port'] ?? env('DB_PORT') ?? env('MYSQLPORT', '3306')));
define('DB_NAME', $db['name'] ?: (env('DB_NAME') ?? env('MYSQLDATABASE', 'skillswap')));
define('DB_USER', $db['user'] ?? env('DB_USER') ?? env('MYSQLUSER', 'root'));
define('DB_PASS', $db['pass'] ?? env('DB_PASS') ?? env('MYSQLPASSWORD', ''));

/* ---------- Profile photos ---------- */
// Photos are stored in the database (profiles.avatar_data), so the app keeps no files and survives
// redeploys and restarts on any host. The browser shrinks photos before upload; this is the server-side cap.
// AVATAR_DIR is only read by database/install.php to import photos saved by older versions.
define('AVATAR_DIR', rtrim(env('UPLOAD_DIR', __DIR__ . '/../uploads/avatars'), '/\\') . DIRECTORY_SEPARATOR);
define('AVATAR_MAX_BYTES', 1024 * 1024);

/* ---------- Behaviour limits ---------- */
define('SKILLS_PER_PAGE', 12);
define('MAX_SKILLS_PER_USER', env_int('MAX_SKILLS_PER_USER', 50));

// Rate limits: [max attempts, window in seconds]
define('RATE_LOGIN',       [env_int('RATE_LOGIN_MAX', 5),        15 * 60]); // per IP + email
define('RATE_LOGIN_IP',    [env_int('RATE_LOGIN_IP_MAX', 60),    15 * 60]); // per IP, any email
define('RATE_REGISTER',    [env_int('RATE_REGISTER_MAX', 30),    60 * 60]); // per IP
define('RATE_REQUEST',     [env_int('RATE_REQUEST_MAX', 20),     60 * 60]); // per user (exchange requests)
define('RATE_PASSWORD',    [5, 15 * 60]);                                   // per user (change password)

// Set to 1 when the app runs behind a reverse proxy/load balancer (Railway, Render, nginx...),
// so the visitor's IP is read from X-Forwarded-For and https is detected from X-Forwarded-Proto.
define('TRUST_PROXY', env('TRUST_PROXY', APP_ENV === 'production' ? '1' : '0') === '1');
