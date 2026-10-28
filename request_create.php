<?php
require __DIR__ . '/includes/init.php';
require_login();

if (is_post()) {
    verify_csrf();
}

$me = current_user();
$skillId = is_post() ? post_int('skill_id') : get_int('skill_id');

$stmt = db()->prepare(skill_select_sql() . ' WHERE s.id = ?');
$stmt->execute([$skillId]);
$skill = $stmt->fetch();

if (!$skill || $skill['owner_status'] !== 'active') {
    abort(404, 'That skill listing no longer exists.');
}
if ((int) $skill['user_id'] === (int) $me['id']) {
    flash('error', 'You cannot send an exchange request for your own skill.');
    redirect('profile.php#skills');
}
if ($skill['type'] !== 'teach') {
    flash('error', 'Exchange requests can only be sent for skills someone offers to teach.');
    redirect('profile.php?id=' . (int) $skill['user_id']);
}

$stmt = db()->prepare(
    "SELECT status FROM exchange_requests
     WHERE sender_id = ? AND skill_id = ? AND status IN ('pending', 'accepted')
     LIMIT 1"
);
$stmt->execute([$me['id'], $skill['id']]);
$activeStatus = $stmt->fetchColumn();
if ($activeStatus) {
    flash('info', $activeStatus === 'pending'
        ? 'You already have a pending request for “' . $skill['title'] . '”.'
        : 'Your exchange for “' . $skill['title'] . '” is already accepted.');
    redirect('requests.php?tab=outgoing');
}

// The sender's own teaching skills, offered in return.
$stmt = db()->prepare("SELECT id, title FROM skills WHERE user_id = ? AND type = 'teach' ORDER BY title");
$stmt->execute([$me['id']]);
$myTeachSkills = $stmt->fetchAll();
$myTeachIds = array_map('intval', array_column($myTeachSkills, 'id'));

$values = ['message' => '', 'offered_skill_id' => 0];
$errors = [];

if (is_post()) {
    $values['message'] = post_str('message');
    $values['offered_skill_id'] = post_int('offered_skill_id');

    if ($values['message'] === '') {
        $errors['message'] = 'Please write a short message introducing yourself.';
    } elseif (mb_strlen($values['message']) < 10 || mb_strlen($values['message']) > 1000) {
        $errors['message'] = 'Message must be between 10 and 1000 characters.';
    }
    if ($values['offered_skill_id'] && !in_array($values['offered_skill_id'], $myTeachIds, true)) {
        $errors['offered_skill_id'] = 'Please choose one of your own teaching skills.';
    }

    if (!$errors && rate_blocked('request', (string) $me['id'], RATE_REQUEST)) {
        $errors['message'] = 'You have sent a lot of requests in the last hour. Please wait a little before sending more.';
    }

    if (!$errors) {
        rate_hit('request', (string) $me['id']);
        db()->prepare(
            'INSERT INTO exchange_requests (sender_id, receiver_id, skill_id, offered_skill_id, message)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $me['id'],
            $skill['user_id'],
            $skill['id'],
            $values['offered_skill_id'] ?: null,
            $values['message'],
        ]);
        flash('success', 'Request sent to ' . first_name($skill['owner_name']) . '. You will see their reply here once they respond.');
        redirect('requests.php?tab=outgoing');
    }
}

$pageTitle = 'Request a skill swap';
require __DIR__ . '/includes/header.php';
?>
<div class="container page page--narrow">
    <div class="page-head">
        <div>
            <a class="back-link" href="profile.php?id=<?= (int) $skill['user_id'] ?>"><?= icon('chevron-left', 16) ?> Back to <?= e(first_name($skill['owner_name'])) ?>'s profile</a>
            <h1>Request a skill swap</h1>
            <p class="muted">Tell <?= e(first_name($skill['owner_name'])) ?> what you would like to learn and what you can offer in return.</p>
        </div>
    </div>

    <div class="request-summary card">
        <a class="request-summary__person" href="profile.php?id=<?= (int) $skill['user_id'] ?>">
            <?= avatar($skill['owner_name'], $skill['owner_avatar'], 'lg') ?>
            <span>
                <strong><?= e($skill['owner_name']) ?></strong>
                <?php if ($skill['owner_course']): ?><small><?= e($skill['owner_course']) ?></small><?php endif; ?>
            </span>
        </a>
        <div class="request-summary__skill">
            <span class="chip chip--muted"><?= e($skill['category_icon'] . ' ' . $skill['category_name']) ?></span>
            <h2><?= e($skill['title']) ?></h2>
            <p class="muted"><?= e(SKILL_LEVELS[$skill['level']]) ?> · <?= e(SKILL_MODES[$skill['mode']]) ?></p>
            <?php if ($skill['description']): ?><p><?= e(excerpt($skill['description'], 220)) ?></p><?php endif; ?>
        </div>
    </div>

    <form method="post" action="request_create.php" class="form card" data-validate novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="skill_id" value="<?= (int) $skill['id'] ?>">

        <div class="form-group">
            <label for="offered_skill_id">What can you teach in return? <span class="optional">optional</span></label>
            <?php if ($myTeachSkills): ?>
                <select id="offered_skill_id" name="offered_skill_id"<?= invalid_attr($errors, 'offered_skill_id') ?>>
                    <option value="">Nothing specific: I just want to learn</option>
                    <?php foreach ($myTeachSkills as $own): ?>
                        <option value="<?= (int) $own['id'] ?>"<?= $values['offered_skill_id'] === (int) $own['id'] ? ' selected' : '' ?>><?= e($own['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'offered_skill_id') ?>
            <?php else: ?>
                <p class="hint">You have not listed any skills you can teach yet. <a href="skill_form.php?type=teach">Add one</a> to make your request a two-way swap.</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="message">Message</label>
            <textarea id="message" name="message" rows="6" required minlength="10" maxlength="1000" data-counter data-label="Message"
                      placeholder="Hi <?= e(first_name($skill['owner_name'])) ?>! I'm learning … and would love your help with … I'm free on …"<?= invalid_attr($errors, 'message') ?>><?= e($values['message']) ?></textarea>
            <?= field_error($errors, 'message') ?>
        </div>

        <div class="form-actions">
            <a class="btn btn--ghost" href="profile.php?id=<?= (int) $skill['user_id'] ?>">Cancel</a>
            <button class="btn btn--primary" type="submit"><?= icon('send', 16) ?> Send request</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
