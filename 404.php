<?php
/**
 * Error page. Used directly for "page not found" and through abort() for other errors.
 */
require_once __DIR__ . '/includes/init.php';

$errorCode = $errorCode ?? 404;
$titles = [
    400 => 'Something went wrong with that request',
    403 => 'You don\'t have access to this',
    404 => 'Page not found',
];
$errorTitle = $titles[$errorCode] ?? 'Something went wrong';
$errorMessage = ($errorMessage ?? '') !== '' ? $errorMessage : 'The page you are looking for does not exist or may have been moved.';

if (!headers_sent()) {
    http_response_code($errorCode);
}

$pageTitle = $errorTitle;
require __DIR__ . '/includes/header.php';
?>
<div class="container page error-page">
    <span class="error-page__code"><?= (int) $errorCode ?></span>
    <h1><?= e($errorTitle) ?></h1>
    <p class="muted"><?= e($errorMessage) ?></p>
    <div class="error-page__actions">
        <a class="btn btn--primary" href="index.php"><?= icon('home', 16) ?> Go home</a>
        <a class="btn btn--secondary" href="skills.php"><?= icon('search', 16) ?> Browse skills</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
