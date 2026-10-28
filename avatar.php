<?php
/**
 * Serves an uploaded profile photo. The upload folder may live outside the web root,
 * so photos are streamed from here after the file name is strictly validated.
 */
require_once __DIR__ . '/includes/config.php';

$file = $_GET['f'] ?? '';
if (!is_string($file) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file) || !is_file(AVATAR_DIR . $file)) {
    http_response_code(404);
    exit;
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$path = AVATAR_DIR . $file;
$etag = '"' . md5_file($path) . '"';

header('Content-Type: ' . $types[pathinfo($file, PATHINFO_EXTENSION)]);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
// File names are random and never reused, so they can be cached for a long time.
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Length: ' . filesize($path));
readfile($path);
