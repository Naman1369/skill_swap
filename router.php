<?php
/**
 * Router for PHP's built-in development server (does the job of .htaccess):
 *   C:\xampp\php\php.exe -S localhost:8000 router.php
 */

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Internal folders and files are never served.
if (preg_match('#^/(includes|database|docs)(/|$)#', $path) || preg_match('#\.(sql|md|htaccess)$#i', $path)
    || $path === '/router.php') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return true;
}

// Uploaded avatars: images only, never executed.
if (str_starts_with($path, '/uploads/') && !preg_match('#^/uploads/avatars/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return true;
}

$file = realpath(__DIR__ . $path);
if ($path === '/' || ($file !== false && str_starts_with($file, __DIR__) && is_file($file))) {
    return false; // let the built-in server handle existing files (and "/" -> index.php)
}

http_response_code(404);
require __DIR__ . '/404.php';
return true;
