<?php
require __DIR__ . '/includes/init.php';

$profileId = get_int('id');
if ($profileId === 0) {
    // "My profile": requires login.
    require_login();
    $profileId = (int) current_user()['id'];
}

$stmt = db()->prepare(
    'SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
            p.course, p.year_of_study, p.college, p.bio, p.phone, p.contact_link, p.avatar
     FROM users u
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE u.id = ?'
);
$stmt->execute([$profileId]);
$person = $stmt->fetch();
// Suspended accounts are hidden from everyone except administrators.
if (!$person || ($person['status'] !== 'active' && !is_admin())) {
    abort(404, 'We could not find that student.');
}

$viewerId = current_user_id();
$isOwn = $viewerId === (int) $person['id'];
$showContact = can_view_contact($viewerId, (int) $person['id']);

$stmt = db()->prepare(skill_select_sql() . ' WHERE s.user_id = ? ORDER BY s.created_at DESC, s.id DESC');
$stmt->execute([$person['id']]);
$skills = $stmt->fetchAll();
$teach = array_values(array_filter($skills, fn ($s) => $s['type'] === 'teach'));
$learn = array_values(array_filter($skills, fn ($s) => $s['type'] === 'learn'));

$stmt = db()->prepare("SELECT COUNT(*) FROM exchange_requests WHERE status = 'completed' AND (sender_id = ? OR receiver_id = ?)");
$stmt->execute([$person['id'], $person['id']]);
$completedCount = (int) $stmt->fetchColumn();

$pageTitle = $isOwn ? 'My profile' : $person['name'];
$activeNav = $isOwn ? 'profile' : '';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <section class="profile-hero card">
        <?= avatar($person['name'], $person['avatar'], 'xl') ?>
        <div class="profile-hero__body">
            <h1><?= e($person['name']) ?><?= $isOwn ? ' <span class="badge badge--muted">You</span>' : '' ?>
                <?php if ($person['role'] === ROLE_ADMIN): ?><span class="badge badge--mutual">Admin</span><?php endif; ?>
                <?php if ($person['status'] !== 'active'): ?><span class="badge badge--rejected">Suspended</span><?php endif; ?></h1>
            <?php
            $academic = array_filter([$person['course'], $person['year_of_study']]);
            ?>
            <?php if ($academic || $person['college']): ?>
                <p class="profile-hero__sub">
                    <?= icon('book', 16) ?>
                    <?= e(implode(' · ', $academic)) ?><?= $academic && $person['college'] ? ' at ' : '' ?><?= e($person['college']) ?>
                </p>
            <?php endif; ?>
            <ul class="profile-hero__stats">
                <li><strong><?= count($teach) ?></strong> can teach</li>
                <li><strong><?= count($learn) ?></strong> want to learn</li>
                <li><strong><?= $completedCount ?></strong> completed swap<?= $completedCount === 1 ? '' : 's' ?></li>
                <li class="muted"><?= icon('calendar', 14) ?> Joined <?= e(date('M Y', strtotime($person['created_at']))) ?></li>
            </ul>
            <?php if ($person['bio']): ?>
                <p class="profile-hero__bio"><?= nl2br(e($person['bio'])) ?></p>
            <?php elseif ($isOwn): ?>
                <p class="profile-hero__bio muted">You haven't written a bio yet. Tell other students what you're studying and what you enjoy.</p>
            <?php endif; ?>
        </div>
        <?php if ($isOwn): ?>
            <div class="profile-hero__actions">
                <a class="btn btn--secondary" href="profile_edit.php"><?= icon('edit', 16) ?> Edit profile</a>
                <a class="btn btn--primary" href="skill_form.php"><?= icon('plus', 16) ?> Add skill</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="contact-card card">
        <h2 class="card__title"><?= icon('mail', 18) ?> Contact</h2>
        <?php if ($showContact): ?>
            <ul class="contact-list">
                <li><?= icon('mail', 16) ?> <a href="mailto:<?= e($person['email']) ?>"><?= e($person['email']) ?></a></li>
                <?php if ($person['phone']): ?>
                    <li><?= icon('phone', 16) ?> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $person['phone'])) ?>"><?= e($person['phone']) ?></a></li>
                <?php endif; ?>
                <?php if ($person['contact_link']): ?>
                    <li><?= icon('link', 16) ?> <a href="<?= e($person['contact_link']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e(preg_replace('#^https?://#', '', $person['contact_link'])) ?></a></li>
                <?php endif; ?>
            </ul>
            <?php if ($isOwn): ?>
                <p class="hint">Only students you have an accepted exchange with can see these details.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="locked"><?= icon('lock', 16) ?> Contact details are shared once <?= e(first_name($person['name'])) ?> accepts an exchange request with you.</p>
        <?php endif; ?>
    </section>

    <section class="section" id="skills">
        <div class="section__head">
            <h2><span class="dot dot--teach"></span> Can teach</h2>
            <?php if ($isOwn): ?>
                <a class="btn btn--ghost btn--sm" href="skill_form.php?type=teach"><?= icon('plus', 15) ?> Add a skill to teach</a>
            <?php endif; ?>
        </div>
        <?php if ($teach): ?>
            <div class="card-grid">
                <?php foreach ($teach as $skill) { skill_card($skill, ['show_owner' => false]); } ?>
            </div>
        <?php else: ?>
            <?php empty_state('award', 'No teaching skills yet',
                $isOwn ? 'Everyone is good at something. Add a skill you could explain to a classmate.' : first_name($person['name']) . ' has not listed any skills to teach yet.',
                $isOwn ? '<a class="btn btn--primary" href="skill_form.php?type=teach">Add a skill</a>' : ''); ?>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section__head">
            <h2><span class="dot dot--learn"></span> Wants to learn</h2>
            <?php if ($isOwn): ?>
                <a class="btn btn--ghost btn--sm" href="skill_form.php?type=learn"><?= icon('plus', 15) ?> Add a skill to learn</a>
            <?php endif; ?>
        </div>
        <?php if ($learn): ?>
            <div class="card-grid">
                <?php foreach ($learn as $skill) { skill_card($skill, ['show_owner' => false]); } ?>
            </div>
        <?php else: ?>
            <?php empty_state('book', 'Nothing on the learning list',
                $isOwn ? 'Add the skills you want to pick up and we will suggest students who can teach them.' : first_name($person['name']) . ' has not listed anything to learn yet.',
                $isOwn ? '<a class="btn btn--primary" href="skill_form.php?type=learn">Add a skill to learn</a>' : ''); ?>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
