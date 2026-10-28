<?php
/**
 * Database-backed rate limiting (works across requests, processes and containers).
 * Each failed or counted action inserts a row; a limit is reached when too many rows exist inside the window.
 * Identifiers are hashed so emails and IPs are not stored in clear text.
 */

/** Is the connection using https (directly or through a trusted reverse proxy)? */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (TRUST_PROXY) {
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
        return $proto === 'https';
    }
    return false;
}

/**
 * The visitor's IP address. Behind a trusted proxy we use the LAST X-Forwarded-For entry,
 * which is the one the proxy itself added; earlier entries can be forged by the visitor.
 */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (TRUST_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $last = end($parts);
        if ($last !== false && filter_var($last, FILTER_VALIDATE_IP)) {
            $ip = $last;
        }
    }
    return $ip;
}

function rate_key(string $ident): string
{
    return hash('sha256', mb_strtolower($ident));
}

/** @param array{0:int,1:int} $limit [max attempts, window seconds] */
function rate_blocked(string $bucket, string $ident, array $limit): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM rate_limits
         WHERE bucket = ? AND ident = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$bucket, rate_key($ident), $limit[1]]);
    return (int) $stmt->fetchColumn() >= $limit[0];
}

function rate_hit(string $bucket, string $ident): void
{
    db()->prepare('INSERT INTO rate_limits (bucket, ident) VALUES (?, ?)')->execute([$bucket, rate_key($ident)]);

    // Housekeeping: now and then, drop rows that are far outside any window.
    if (random_int(1, 100) === 1) {
        db()->exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function rate_clear(string $bucket, string $ident): void
{
    db()->prepare('DELETE FROM rate_limits WHERE bucket = ? AND ident = ?')->execute([$bucket, rate_key($ident)]);
}

/** Human friendly "try again in …" text for a blocked bucket. */
function rate_wait_text(string $bucket, string $ident, array $limit): string
{
    $stmt = db()->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, NOW(), MIN(created_at) + INTERVAL ? SECOND) FROM rate_limits
         WHERE bucket = ? AND ident = ? AND created_at > (NOW() - INTERVAL ? SECOND)'
    );
    $stmt->execute([$limit[1], $bucket, rate_key($ident), $limit[1]]);
    $seconds = max(1, (int) $stmt->fetchColumn());
    return $seconds >= 90 ? 'about ' . (int) ceil($seconds / 60) . ' minutes' : $seconds . ' seconds';
}
