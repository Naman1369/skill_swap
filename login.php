<?php
require __DIR__ . '/includes/init.php';
require_guest();

$email = '';
$errors = [];

if (is_post()) {
    verify_csrf();

    $email = mb_strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');

    // Failed attempts are counted in the database per IP + email and per IP, so clearing cookies does not reset them.
    $ip = client_ip();
    $pairKey = $ip . '|' . $email;

    $blockedPair = rate_blocked('login', $pairKey, RATE_LOGIN);
    $blockedIp = !$blockedPair && rate_blocked('login_ip', $ip, RATE_LOGIN_IP);

    if ($blockedPair || $blockedIp) {
        $wait = $blockedPair ? rate_wait_text('login', $pairKey, RATE_LOGIN) : rate_wait_text('login_ip', $ip, RATE_LOGIN_IP);
        $errors['form'] = "Too many failed attempts. Please wait {$wait} and try again.";
    } elseif ($email === '' || $password === '') {
        if ($email === '') {
            $errors['email'] = 'Please enter your email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }
    } else {
        $stmt = db()->prepare('SELECT id, name, password_hash, role, status FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Always run one password check, so response time does not reveal whether the email exists.
        $hash = $user['password_hash'] ?? '$2y$10$JGrpf7TdojXaTdyYciF5f.A3t9jqfHY3JdGgx8bdL6I9YjTmUzMte';
        $passwordOk = password_verify($password, $hash) && $user;

        if ($passwordOk && $user['status'] === 'active') {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            rate_clear('login', $pairKey);
            $target = take_intended_url($user['role'] === ROLE_ADMIN ? 'admin.php' : 'dashboard.php');
            login_user((int) $user['id']);
            flash('success', 'Welcome back, ' . first_name($user['name']) . '!');
            redirect($target);
        }

        if ($passwordOk) {
            // Correct password, but the account was suspended by an administrator.
            $errors['form'] = 'This account has been suspended. Please contact an administrator.';
        } else {
            // Same message whether the email or the password was wrong.
            $errors['form'] = 'Invalid email or password.';
            rate_hit('login', $pairKey);
            rate_hit('login_ip', $ip);
        }
    }
}

// Show demo credentials only when the demo data has been loaded.
$demoStmt = db()->prepare('SELECT 1 FROM users WHERE email = ?');
$demoStmt->execute(['aarav@skillswap.test']);
$hasDemo = (bool) $demoStmt->fetchColumn();

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
    <div class="auth__panel auth__panel--intro">
        <h1>Welcome back</h1>
        <p>Log in to manage your skills, check exchange requests and find your next learning partner.</p>
        <?php if ($hasDemo): ?>
            <div class="demo-box">
                <strong><?= icon('zap', 16) ?> Demo account</strong>
                <span>Email: <code>aarav@skillswap.test</code></span>
                <span>Password: <code>Password@123</code></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="auth__panel card">
        <h2>Log in</h2>
        <p class="muted">New to SkillSwap? <a href="register.php">Create an account</a></p>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert--error" role="alert"><?= icon('alert', 18) ?> <?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" class="form" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       data-label="Email" value="<?= e($email) ?>"<?= invalid_attr($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           data-label="Password"<?= invalid_attr($errors, 'password') ?>>
                    <button type="button" class="password-field__toggle" data-toggle-password="#password" aria-label="Show password"><?= icon('eye', 18) ?></button>
                </div>
                <?= field_error($errors, 'password') ?>
            </div>

            <button class="btn btn--primary btn--block btn--lg" type="submit">Log in</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
