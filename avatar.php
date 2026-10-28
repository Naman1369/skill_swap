<?php
/**
 * Serves a profile photo from the database. File names are random and never reused,
 * so responses can be cached for a long time.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$file = $_GET['f'] ?? '';
if (!is_string($file) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file)) {
    http_response_code(404);
    exit;
}

$etag = '"' . $file . '"';
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$stmt = db()->prepare('SELECT avatar_mime, avatar_data FROM profiles WHERE avatar = ? AND avatar_data IS NOT NULL');
$stmt->execute([$file]);
$photo = $stmt->fetch();

if (!$photo) {
    header_remove('Cache-Control');
    header_remove('ETag');
    header('Cache-Control: no-store');
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $photo['avatar_mime']);
header('Content-Length: ' . strlen($photo['avatar_data']));
echo $photo['avatar_data'];
