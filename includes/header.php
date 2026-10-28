<?php
/**
 * Shared page header. Pages set $pageTitle and (optionally) $activeNav before including it.
 */
$pageTitle = $pageTitle ?? '';
$activeNav = $activeNav ?? '';
$me = current_user();
$pendingCount = $me ? pending_incoming_count((int) $me['id']) : 0;

$navLink = function (string $href, string $label, string $key, string $extraHtml = '') use ($activeNav): string {
    $active = $activeNav === $key;
    return '<a class="site-nav__link' . ($active ? ' is-active' : '') . '" href="' . e($href) . '"'
        . ($active ? ' aria-current="page"' : '') . '>' . e($label) . $extraHtml . '</a>';
};
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle !== '' ? $pageTitle . ' · ' . APP_NAME : APP_NAME . ' · Student Skill Exchange') ?></title>
    <meta name="description" content="SkillSwap helps students share what they can teach, find peers who can teach them, and organise skill exchanges.">
    <link rel="icon" href="assets/logo.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="css/style.css">
    <script nonce="<?= e(csp_nonce()) ?>">
        (function () {
            try {
                var theme = localStorage.getItem('skillswap-theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-theme', theme);
                }
            } catch (e) {}
        })();
    </script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="index.php">
            <img src="assets/logo.svg" alt="" width="32" height="32">
            <span>SkillSwap</span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Toggle menu">
            <?= icon('menu', 22) ?>
        </button>

        <nav class="site-nav" id="site-nav" aria-label="Main">
            <div class="site-nav__links">
                <?= $navLink('skills.php', 'Browse skills', 'skills') ?>
                <?php if ($me): ?>
                    <?= $navLink('dashboard.php', 'Dashboard', 'dashboard') ?>
                    <?= $navLink('requests.php', 'Requests', 'requests',
                        $pendingCount > 0 ? ' <span class="nav-badge" aria-label="' . $pendingCount . ' pending">' . $pendingCount . '</span>' : '') ?>
                    <?= $navLink('profile.php', 'My profile', 'profile') ?>
                    <?php if (is_admin()): ?>
                        <?= $navLink('admin.php', 'Admin', 'admin') ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="site-nav__actions">
                <button class="icon-btn theme-toggle" type="button" aria-label="Switch colour theme" title="Switch colour theme">
                    <span class="theme-toggle__sun"><?= icon('sun', 18) ?></span>
                    <span class="theme-toggle__moon"><?= icon('moon', 18) ?></span>
                </button>
                <?php if ($me): ?>
                    <a class="site-nav__me" href="profile.php" title="<?= e($me['name']) ?>">
                        <?= avatar($me['name'], $me['avatar'], 'sm') ?>
                        <span class="site-nav__me-name"><?= e(first_name($me['name'])) ?></span>
                    </a>
                    <form method="post" action="logout.php" class="site-nav__logout">
                        <?= csrf_field() ?>
                        <button class="btn btn--ghost btn--sm" type="submit"><?= icon('logout', 16) ?> Log out</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn--ghost btn--sm" href="login.php">Log in</a>
                    <a class="btn btn--primary btn--sm" href="register.php">Sign up free</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<main id="main" class="site-main">
<?php $flashes = take_flashes(); ?>
<?php if ($flashes): ?>
    <div class="container flash-stack" role="status" aria-live="polite">
        <?php foreach ($flashes as $flash): ?>
            <?php $kind = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info'; ?>
            <div class="flash flash--<?= e($kind) ?>">
                <?= icon($kind === 'success' ? 'check-circle' : ($kind === 'error' ? 'alert' : 'info'), 18) ?>
                <p><?= e($flash['message']) ?></p>
                <button class="flash__close" type="button" aria-label="Dismiss"><?= icon('x', 16) ?></button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
