<?php
require __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$errors = [];

if (is_post()) {
    verify_csrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (rate_blocked('password', (string) $me['id'], RATE_PASSWORD)) {
        $errors['form'] = 'Too many incorrect attempts. Please wait ' . rate_wait_text('password', (string) $me['id'], RATE_PASSWORD) . ' and try again.';
    } else {
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$me['id']]);
        $hash = (string) $stmt->fetchColumn();

        if ($current === '') {
            $errors['current_password'] = 'Please enter your current password.';
        } elseif (!password_verify($current, $hash)) {
            $errors['current_password'] = 'That is not your current password.';
            rate_hit('password', (string) $me['id']);
        }

        if ($new === '') {
            $errors['new_password'] = 'Please choose a new password.';
        } elseif (strlen($new) < 8 || strlen($new) > 72) {
            $errors['new_password'] = 'Password must be between 8 and 72 characters.';
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['new_password'] = 'Password must contain at least one letter and one number.';
        } elseif ($new === $current) {
            $errors['new_password'] = 'Your new password must be different from the current one.';
        }

        if (!isset($errors['new_password']) && $new !== $confirm) {
            $errors['new_password_confirm'] = 'Passwords do not match.';
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            rate_clear('password', (string) $me['id']);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'Change password';
$activeNav = 'profile';
require __DIR__ . '/includes/header.php';
?>
<div class="container page page--narrow">
    <div class="page-head">
        <div>
            <a class="back-link" href="profile_edit.php"><?= icon('chevron-left', 16) ?> Back to edit profile</a>
            <h1>Change password</h1>
            <p class="muted">Choose a password you do not use on other sites.</p>
        </div>
    </div>

    <form method="post" action="change_password.php" class="form card" data-validate novalidate>
        <?= csrf_field() ?>

        <?php if (!empty($errors['form'])): ?>
            <div class="alert alert--error" role="alert"><?= icon('alert', 18) ?> <?= e($errors['form']) ?></div>
        <?php endif; ?>

        <div class="form-group">
            <label for="current_password">Current password</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required
                   data-label="Current password"<?= invalid_attr($errors, 'current_password') ?>>
            <?= field_error($errors, 'current_password') ?>
        </div>

        <div class="form-group">
            <label for="new_password">New password</label>
            <div class="password-field">
                <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8" maxlength="72"
                       data-label="New password" data-pattern="^(?=.*[A-Za-z])(?=.*\d).+$"
                       data-pattern-message="Password must contain at least one letter and one number."<?= invalid_attr($errors, 'new_password') ?>>
                <button type="button" class="password-field__toggle" data-toggle-password="#new_password" aria-label="Show password"><?= icon('eye', 18) ?></button>
            </div>
            <p class="hint">At least 8 characters, with a letter and a number.</p>
            <?= field_error($errors, 'new_password') ?>
        </div>

        <div class="form-group">
            <label for="new_password_confirm">Confirm new password</label>
            <input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" required
                   data-label="Password confirmation" data-match="#new_password"<?= invalid_attr($errors, 'new_password_confirm') ?>>
            <?= field_error($errors, 'new_password_confirm') ?>
        </div>

        <div class="form-actions">
            <a class="btn btn--ghost" href="profile_edit.php">Cancel</a>
            <button class="btn btn--primary" type="submit"><?= icon('lock', 16) ?> Change password</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
