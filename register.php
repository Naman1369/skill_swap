<?php
require __DIR__ . '/includes/init.php';
require_guest();

$values = ['name' => '', 'email' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();

    $values['name'] = post_str('name');
    $values['email'] = mb_strtolower(post_str('email'));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    // --- Server-side validation (never rely on browser checks alone) ---
    if ($values['name'] === '') {
        $errors['name'] = 'Please enter your full name.';
    } elseif (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 190) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Please choose a password.';
    } elseif (strlen($password) < 8 || strlen($password) > 72) {
        $errors['password'] = 'Password must be between 8 and 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }

    if (!isset($errors['password']) && $password !== $confirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    if (!isset($errors['email'])) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$values['email']]);
        if ($stmt->fetchColumn()) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        }
    }

    if (!$errors && rate_blocked('register', client_ip(), RATE_REGISTER)) {
        $errors['form'] = 'Too many accounts have been created from your network recently. Please try again later.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$values['name'], $values['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO profiles (user_id) VALUES (?)')->execute([$userId]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                // Someone registered the same email between our check and the insert.
                $errors['email'] = 'An account with this email already exists. Try logging in instead.';
            } else {
                throw $e;
            }
        }

        if (!$errors) {
            rate_hit('register', client_ip());
            login_user($userId);
            flash('success', 'Welcome to SkillSwap, ' . first_name($values['name']) . '! Complete your profile so other students can find you.');
            redirect('profile_edit.php');
        }
    }
}

$pageTitle = 'Create your account';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
    <div class="auth__panel auth__panel--intro">
        <h1>Join the student skill exchange</h1>
        <p>Share what you're good at, learn what you're curious about, and meet peers who can help.</p>
        <ul class="auth__points">
            <li><?= icon('check', 18) ?> List skills you can teach and want to learn</li>
            <li><?= icon('check', 18) ?> Find peers by skill, category and level</li>
            <li><?= icon('check', 18) ?> Send and manage exchange requests</li>
        </ul>
    </div>

    <div class="auth__panel card">
        <h2>Create your account</h2>
        <p class="muted">Already have one? <a href="login.php">Log in</a></p>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert--error" role="alert"><?= icon('alert', 18) ?> <?= e($errors['form']) ?></div>
        <?php endif; ?>

        <form method="post" action="register.php" class="form" data-validate novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="100"
                       data-label="Full name" value="<?= e($values['name']) ?>"<?= invalid_attr($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required maxlength="190"
                       data-label="Email" value="<?= e($values['email']) ?>"<?= invalid_attr($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8" maxlength="72"
                           data-label="Password" data-pattern="^(?=.*[A-Za-z])(?=.*\d).+$"
                           data-pattern-message="Password must contain at least one letter and one number."<?= invalid_attr($errors, 'password') ?>>
                    <button type="button" class="password-field__toggle" data-toggle-password="#password" aria-label="Show password"><?= icon('eye', 18) ?></button>
                </div>
                <p class="hint">At least 8 characters, with a letter and a number.</p>
                <?= field_error($errors, 'password') ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required
                       data-label="Password confirmation" data-match="#password"<?= invalid_attr($errors, 'password_confirm') ?>>
                <?= field_error($errors, 'password_confirm') ?>
            </div>

            <button class="btn btn--primary btn--block btn--lg" type="submit">Create account</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
