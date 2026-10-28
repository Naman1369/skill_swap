<?php
require __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$fields = ['name', 'course', 'year_of_study', 'college', 'bio', 'phone', 'contact_link'];
$values = [];
foreach ($fields as $field) {
    $values[$field] = (string) ($me[$field] ?? '');
}
$errors = [];

if (is_post()) {
    verify_csrf();

    foreach ($fields as $field) {
        $values[$field] = post_str($field);
    }

    // --- Validation ---
    if ($values['name'] === '') {
        $errors['name'] = 'Please enter your full name.';
    } elseif (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
    }
    if (mb_strlen($values['course']) > 100) {
        $errors['course'] = 'Course must be 100 characters or fewer.';
    }
    if ($values['year_of_study'] !== '' && !in_array($values['year_of_study'], YEARS_OF_STUDY, true)) {
        $errors['year_of_study'] = 'Please choose a year from the list.';
    }
    if (mb_strlen($values['college']) > 150) {
        $errors['college'] = 'College name must be 150 characters or fewer.';
    }
    if (mb_strlen($values['bio']) > 1000) {
        $errors['bio'] = 'Bio must be 1000 characters or fewer.';
    }
    if ($values['phone'] !== '' && !preg_match('/^\+?[0-9][0-9 \-]{6,18}$/', $values['phone'])) {
        $errors['phone'] = 'Enter a valid phone number (digits, spaces, dashes and an optional leading +).';
    }
    if ($values['contact_link'] !== '') {
        $scheme = strtolower((string) parse_url($values['contact_link'], PHP_URL_SCHEME));
        if (!filter_var($values['contact_link'], FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true) || strlen($values['contact_link']) > 255) {
            $errors['contact_link'] = 'Enter a full link starting with https:// (for example your LinkedIn or GitHub).';
        }
    }

    // --- Optional profile photo ---
    $newAvatar = null;
    $upload = $_FILES['avatar'] ?? null;
    if ($upload && is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $errors['avatar'] = 'The photo could not be uploaded. Please try a smaller image.';
        } elseif ($upload['size'] > AVATAR_MAX_BYTES) {
            $errors['avatar'] = 'Photo must be 2 MB or smaller.';
        } else {
            // Inspect the file contents, never trust the browser-supplied name or type.
            $info = @getimagesize($upload['tmp_name']);
            $mime = $info['mime'] ?? '';
            if ($info === false || !isset($allowed[$mime])) {
                $errors['avatar'] = 'Photo must be a JPG, PNG or WebP image.';
            } else {
                $newAvatar = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            }
        }
    }

    if (!$errors) {
        $avatar = $me['avatar'];

        if ($newAvatar !== null) {
            if (!is_dir(AVATAR_DIR)) {
                mkdir(AVATAR_DIR, 0755, true);
            }
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], AVATAR_DIR . $newAvatar)) {
                $errors['avatar'] = 'The photo could not be saved. Please try again.';
            } else {
                $avatar = $newAvatar;
            }
        } elseif (!empty($_POST['remove_avatar'])) {
            $avatar = null;
        }

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$values['name'], $me['id']]);
            $pdo->prepare(
                'INSERT INTO profiles (user_id, course, year_of_study, college, bio, phone, contact_link, avatar)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE course = VALUES(course), year_of_study = VALUES(year_of_study),
                     college = VALUES(college), bio = VALUES(bio), phone = VALUES(phone),
                     contact_link = VALUES(contact_link), avatar = VALUES(avatar)'
            )->execute([
                $me['id'],
                $values['course'] ?: null,
                $values['year_of_study'] ?: null,
                $values['college'] ?: null,
                $values['bio'] ?: null,
                $values['phone'] ?: null,
                $values['contact_link'] ?: null,
                $avatar,
            ]);
            $pdo->commit();

            // Remove the previous photo file once it is no longer used.
            if ($me['avatar'] && $me['avatar'] !== $avatar && avatar_url($me['avatar']) !== null) {
                @unlink(AVATAR_DIR . basename($me['avatar']));
            }

            flash('success', 'Your profile has been updated.');
            redirect('profile.php');
        }
    }
}

$pageTitle = 'Edit profile';
$activeNav = 'profile';
require __DIR__ . '/includes/header.php';
?>
<div class="container page page--narrow">
    <div class="page-head">
        <div>
            <a class="back-link" href="profile.php"><?= icon('chevron-left', 16) ?> Back to profile</a>
            <h1>Edit profile</h1>
            <p class="muted">A complete profile helps other students decide to swap skills with you.</p>
        </div>
    </div>

    <form method="post" action="profile_edit.php" enctype="multipart/form-data" class="form card" data-validate novalidate>
        <?= csrf_field() ?>

        <fieldset class="form-section">
            <legend>Profile photo</legend>
            <div class="avatar-upload">
                <div class="avatar-upload__preview" data-avatar-preview>
                    <?= avatar($me['name'], $me['avatar'], 'xl') ?>
                </div>
                <div class="form-group">
                    <label for="avatar">Upload a new photo</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-avatar-input<?= invalid_attr($errors, 'avatar') ?>>
                    <p class="hint">JPG, PNG or WebP, up to 2 MB.</p>
                    <?= field_error($errors, 'avatar') ?>
                    <?php if (avatar_url($me['avatar']) !== null): ?>
                        <label class="checkbox"><input type="checkbox" name="remove_avatar" value="1"> Remove current photo</label>
                    <?php endif; ?>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend>About you</legend>
            <div class="form-group">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" required minlength="2" maxlength="100" data-label="Full name"
                       value="<?= e($values['name']) ?>"<?= invalid_attr($errors, 'name') ?>>
                <?= field_error($errors, 'name') ?>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="course">Course <span class="optional">optional</span></label>
                    <input id="course" name="course" type="text" maxlength="100" placeholder="e.g. BCA (Data Science)"
                           value="<?= e($values['course']) ?>"<?= invalid_attr($errors, 'course') ?>>
                    <?= field_error($errors, 'course') ?>
                </div>
                <div class="form-group">
                    <label for="year_of_study">Year of study <span class="optional">optional</span></label>
                    <select id="year_of_study" name="year_of_study"<?= invalid_attr($errors, 'year_of_study') ?>>
                        <option value="">Select year</option>
                        <?php foreach (YEARS_OF_STUDY as $year): ?>
                            <option value="<?= e($year) ?>"<?= $values['year_of_study'] === $year ? ' selected' : '' ?>><?= e($year) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'year_of_study') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="college">College <span class="optional">optional</span></label>
                <input id="college" name="college" type="text" maxlength="150" placeholder="e.g. MIT School of Business & Computer Applications, Pune"
                       value="<?= e($values['college']) ?>"<?= invalid_attr($errors, 'college') ?>>
                <?= field_error($errors, 'college') ?>
            </div>
            <div class="form-group">
                <label for="bio">Bio <span class="optional">optional</span></label>
                <textarea id="bio" name="bio" rows="5" maxlength="1000" data-counter
                          placeholder="What are you studying? What do you enjoy teaching or learning?"<?= invalid_attr($errors, 'bio') ?>><?= e($values['bio']) ?></textarea>
                <?= field_error($errors, 'bio') ?>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend>Contact details</legend>
            <p class="hint"><?= icon('lock', 14) ?> Your email, phone and link are only shown to students after you have an accepted exchange with them.</p>
            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone / WhatsApp <span class="optional">optional</span></label>
                    <input id="phone" name="phone" type="tel" maxlength="20" placeholder="+91 98765 43210"
                           data-label="Phone" data-pattern="^\+?[0-9][0-9 \-]{6,18}$" data-pattern-message="Enter a valid phone number."
                           value="<?= e($values['phone']) ?>"<?= invalid_attr($errors, 'phone') ?>>
                    <?= field_error($errors, 'phone') ?>
                </div>
                <div class="form-group">
                    <label for="contact_link">Profile link <span class="optional">optional</span></label>
                    <input id="contact_link" name="contact_link" type="url" maxlength="255" placeholder="https://linkedin.com/in/your-name"
                           data-label="Profile link" data-pattern="^https?://.+" data-pattern-message="Link must start with http:// or https://"
                           value="<?= e($values['contact_link']) ?>"<?= invalid_attr($errors, 'contact_link') ?>>
                    <?= field_error($errors, 'contact_link') ?>
                </div>
            </div>
        </fieldset>

        <div class="form-actions">
            <a class="btn btn--ghost" href="profile.php">Cancel</a>
            <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?> Save profile</button>
        </div>
    </form>

    <p class="muted" style="margin-top:16px"><?= icon('lock', 14) ?> <a href="change_password.php">Change your password</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
