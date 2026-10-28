<?php
require __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$skillId = is_post() ? post_int('id') : get_int('id');
$skill = null;

if ($skillId) {
    $stmt = db()->prepare('SELECT * FROM skills WHERE id = ?');
    $stmt->execute([$skillId]);
    $skill = $stmt->fetch();
    if (!$skill) {
        abort(404, 'That skill listing does not exist.');
    }
    if ((int) $skill['user_id'] !== (int) $me['id']) {
        abort(403, 'You can only edit your own skill listings.');
    }
}

$requestedType = get_str('type');
$values = $skill ? [
    'title'       => $skill['title'],
    'description' => (string) $skill['description'],
    'type'        => $skill['type'],
    'category_id' => (int) $skill['category_id'],
    'level'       => $skill['level'],
    'mode'        => $skill['mode'],
] : [
    'title'       => '',
    'description' => '',
    'type'        => isset(SKILL_TYPES[$requestedType]) ? $requestedType : 'teach',
    'category_id' => get_int('category'),
    'level'       => 'beginner',
    'mode'        => 'both',
];
$errors = [];

if (is_post()) {
    verify_csrf();

    $values = [
        'title'       => post_str('title'),
        'description' => post_str('description'),
        'type'        => post_str('type'),
        'category_id' => post_int('category_id'),
        'level'       => post_str('level'),
        'mode'        => post_str('mode'),
    ];

    if ($values['title'] === '') {
        $errors['title'] = 'Please enter the skill name.';
    } elseif (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 100) {
        $errors['title'] = 'Skill name must be between 3 and 100 characters.';
    }
    if (!isset(SKILL_TYPES[$values['type']])) {
        $errors['type'] = 'Choose whether you can teach this skill or want to learn it.';
    }
    if (!category_exists($values['category_id'])) {
        $errors['category_id'] = 'Please choose a category.';
    }
    if (!isset(SKILL_LEVELS[$values['level']])) {
        $errors['level'] = 'Please choose a level.';
    }
    if (!isset(SKILL_MODES[$values['mode']])) {
        $errors['mode'] = 'Please choose how you would like to meet.';
    }
    if (mb_strlen($values['description']) > 1000) {
        $errors['description'] = 'Description must be 1000 characters or fewer.';
    }

    if (!isset($errors['title']) && !isset($errors['type'])) {
        $stmt = db()->prepare('SELECT id FROM skills WHERE user_id = ? AND type = ? AND title = ? AND id <> ?');
        $stmt->execute([$me['id'], $values['type'], $values['title'], $skillId]);
        if ($stmt->fetchColumn()) {
            $errors['title'] = 'You already have this skill on your list.';
        }
    }

    if (!$errors && !$skill) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM skills WHERE user_id = ?');
        $stmt->execute([$me['id']]);
        if ((int) $stmt->fetchColumn() >= MAX_SKILLS_PER_USER) {
            $errors['title'] = 'You have reached the limit of ' . MAX_SKILLS_PER_USER . ' skills. Remove one before adding another.';
        }
    }

    if (!$errors) {
        $params = [
            $values['category_id'],
            $values['title'],
            $values['description'] !== '' ? $values['description'] : null,
            $values['type'],
            $values['level'],
            $values['mode'],
        ];

        if ($skill) {
            $stmt = db()->prepare(
                'UPDATE skills SET category_id = ?, title = ?, description = ?, type = ?, level = ?, mode = ?
                 WHERE id = ? AND user_id = ?'
            );
            $stmt->execute([...$params, $skillId, $me['id']]);
            flash('success', 'Your skill “' . $values['title'] . '” has been updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO skills (category_id, title, description, type, level, mode, user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([...$params, $me['id']]);
            flash('success', $values['type'] === 'teach'
                ? 'Skill added! Other students can now request to learn “' . $values['title'] . '” from you.'
                : '“' . $values['title'] . '” added to your learning list. Check your dashboard for suggested teachers.');
        }
        redirect('profile.php#skills');
    }
}

$pageTitle = $skill ? 'Edit skill' : 'Add a skill';
require __DIR__ . '/includes/header.php';
?>
<div class="container page page--narrow">
    <div class="page-head">
        <div>
            <a class="back-link" href="profile.php#skills"><?= icon('chevron-left', 16) ?> Back to my skills</a>
            <h1><?= $skill ? 'Edit skill' : 'Add a skill' ?></h1>
            <p class="muted">Describe the skill clearly so the right students can find it.</p>
        </div>
    </div>

    <form method="post" action="skill_form.php" class="form card" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $skillId ?>">

        <fieldset class="form-group">
            <legend class="label">I want to list a skill I…</legend>
            <div class="segmented">
                <label class="segmented__option segmented__option--teach">
                    <input type="radio" name="type" value="teach" required<?= $values['type'] === 'teach' ? ' checked' : '' ?>>
                    <span><?= icon('award', 18) ?> <strong>Can teach</strong><small>Share what you know</small></span>
                </label>
                <label class="segmented__option segmented__option--learn">
                    <input type="radio" name="type" value="learn"<?= $values['type'] === 'learn' ? ' checked' : '' ?>>
                    <span><?= icon('book', 18) ?> <strong>Want to learn</strong><small>Find someone to teach you</small></span>
                </label>
            </div>
            <?= field_error($errors, 'type') ?>
        </fieldset>

        <div class="form-group">
            <label for="title">Skill name</label>
            <input id="title" name="title" type="text" required minlength="3" maxlength="100" data-label="Skill name"
                   placeholder="e.g. Python for beginners, Canva poster design, Public speaking"
                   value="<?= e($values['title']) ?>"<?= invalid_attr($errors, 'title') ?>>
            <?= field_error($errors, 'title') ?>
        </div>

        <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required data-label="Category"<?= invalid_attr($errors, 'category_id') ?>>
                <option value="">Choose a category</option>
                <?php foreach (categories() as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= (int) $values['category_id'] === (int) $category['id'] ? ' selected' : '' ?>>
                        <?= e($category['icon'] . ' ' . $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="level">Level</label>
                <select id="level" name="level" required data-label="Level"<?= invalid_attr($errors, 'level') ?>>
                    <?php foreach (SKILL_LEVELS as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $values['level'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint">Your current level if teaching, or the level you are aiming for.</p>
                <?= field_error($errors, 'level') ?>
            </div>
            <div class="form-group">
                <label for="mode">How to meet</label>
                <select id="mode" name="mode" required data-label="Meeting mode"<?= invalid_attr($errors, 'mode') ?>>
                    <?php foreach (SKILL_MODES as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $values['mode'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'mode') ?>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description <span class="optional">optional</span></label>
            <textarea id="description" name="description" rows="5" maxlength="1000" data-counter
                      placeholder="What will you cover or what do you want to learn? Any tools, topics or availability worth mentioning?"<?= invalid_attr($errors, 'description') ?>><?= e($values['description']) ?></textarea>
            <?= field_error($errors, 'description') ?>
        </div>

        <div class="form-actions">
            <a class="btn btn--ghost" href="profile.php#skills">Cancel</a>
            <button class="btn btn--primary" type="submit"><?= icon('check', 16) ?> <?= $skill ? 'Save changes' : 'Add skill' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
