<?php
require __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$myId = (int) $me['id'];
$pdo = db();

// --- Stats ---
$stmt = $pdo->prepare("SELECT SUM(type = 'teach') AS teach, SUM(type = 'learn') AS learn FROM skills WHERE user_id = ?");
$stmt->execute([$myId]);
$skillCounts = $stmt->fetch();
$teachCount = (int) $skillCounts['teach'];
$learnCount = (int) $skillCounts['learn'];

$stmt = $pdo->prepare(
    "SELECT
        SUM(receiver_id = ? AND status = 'pending') AS pending_in,
        SUM(status = 'accepted')                   AS active,
        SUM(status = 'completed')                  AS completed
     FROM exchange_requests
     WHERE sender_id = ? OR receiver_id = ?"
);
$stmt->execute([$myId, $myId, $myId]);
$reqCounts = $stmt->fetch();

$completeness = profile_completeness($me, $teachCount + $learnCount);

// --- Pending requests received ---
$stmt = $pdo->prepare(
    "SELECT r.id, r.message, r.created_at, s.title AS skill_title, os.title AS offered_title,
            u.id AS sender_id, u.name AS sender_name, p.avatar AS sender_avatar
     FROM exchange_requests r
     JOIN skills s ON s.id = r.skill_id
     LEFT JOIN skills os ON os.id = r.offered_skill_id
     JOIN users u ON u.id = r.sender_id
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE r.receiver_id = ? AND r.status = 'pending'
     ORDER BY r.created_at DESC
     LIMIT 4"
);
$stmt->execute([$myId]);
$incoming = $stmt->fetchAll();

// --- Suggested matches ---
// Students who teach something in a category I want to learn.
// "Mutual" means they also want to learn something in a category I teach.
$stmt = $pdo->prepare(
    "SELECT s.id, s.user_id, s.title, s.level, c.icon AS category_icon,
            u.name AS owner_name, p.avatar AS owner_avatar,
            (SELECT 1 FROM skills theirs
               JOIN skills mine ON mine.category_id = theirs.category_id AND mine.user_id = ? AND mine.type = 'teach'
             WHERE theirs.user_id = s.user_id AND theirs.type = 'learn'
             LIMIT 1) AS mutual_match
     FROM skills s
     JOIN categories c ON c.id = s.category_id
     JOIN users u ON u.id = s.user_id
     LEFT JOIN profiles p ON p.user_id = u.id
     WHERE s.type = 'teach'
       AND u.status = 'active'
       AND s.user_id <> ?
       AND s.category_id IN (SELECT category_id FROM skills WHERE user_id = ? AND type = 'learn')
     ORDER BY mutual_match IS NULL, s.created_at DESC
     LIMIT 6"
);
$stmt->execute([$myId, $myId, $myId]);
$suggestions = $stmt->fetchAll();

// --- My skills ---
$stmt = $pdo->prepare(
    'SELECT s.id, s.title, s.type, s.level, c.icon AS category_icon
     FROM skills s JOIN categories c ON c.id = s.category_id
     WHERE s.user_id = ? ORDER BY s.type, s.title'
);
$stmt->execute([$myId]);
$mySkills = $stmt->fetchAll();

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <h1><?= e($greeting) ?>, <?= e(first_name($me['name'])) ?> 👋</h1>
            <p class="muted">Here is what is happening with your skill swaps.</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--secondary" href="skills.php"><?= icon('search', 16) ?> Browse skills</a>
            <a class="btn btn--primary" href="skill_form.php"><?= icon('plus', 16) ?> Add a skill</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Your numbers">
        <a class="stat-card" href="profile.php#skills">
            <span class="stat-card__icon stat-card__icon--teach"><?= icon('award', 20) ?></span>
            <span class="stat-card__value"><?= $teachCount ?></span>
            <span class="stat-card__label">Skills you teach</span>
        </a>
        <a class="stat-card" href="profile.php#skills">
            <span class="stat-card__icon stat-card__icon--learn"><?= icon('book', 20) ?></span>
            <span class="stat-card__value"><?= $learnCount ?></span>
            <span class="stat-card__label">Skills you want to learn</span>
        </a>
        <a class="stat-card" href="requests.php?tab=incoming&amp;status=pending">
            <span class="stat-card__icon stat-card__icon--pending"><?= icon('inbox', 20) ?></span>
            <span class="stat-card__value"><?= (int) $reqCounts['pending_in'] ?></span>
            <span class="stat-card__label">Requests awaiting you</span>
        </a>
        <a class="stat-card" href="requests.php?tab=incoming&amp;status=accepted">
            <span class="stat-card__icon stat-card__icon--active"><?= icon('swap', 20) ?></span>
            <span class="stat-card__value"><?= (int) $reqCounts['active'] ?></span>
            <span class="stat-card__label">Active exchanges</span>
        </a>
        <a class="stat-card" href="requests.php?tab=incoming&amp;status=completed">
            <span class="stat-card__icon stat-card__icon--done"><?= icon('check-circle', 20) ?></span>
            <span class="stat-card__value"><?= (int) $reqCounts['completed'] ?></span>
            <span class="stat-card__label">Completed swaps</span>
        </a>
    </section>

    <div class="dashboard-grid">
        <div class="dashboard-grid__main">
            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><?= icon('inbox', 18) ?> Requests waiting for you</h2>
                    <a class="link-arrow" href="requests.php">All requests <?= icon('arrow-right', 14) ?></a>
                </div>
                <?php if ($incoming): ?>
                    <ul class="mini-requests">
                        <?php foreach ($incoming as $r): ?>
                            <li class="mini-request">
                                <a href="profile.php?id=<?= (int) $r['sender_id'] ?>"><?= avatar($r['sender_name'], $r['sender_avatar'], 'md') ?></a>
                                <div class="mini-request__body">
                                    <p>
                                        <a href="profile.php?id=<?= (int) $r['sender_id'] ?>"><strong><?= e($r['sender_name']) ?></strong></a>
                                        wants to learn <strong><?= e($r['skill_title']) ?></strong>
                                        <?php if ($r['offered_title']): ?> and offers <strong><?= e($r['offered_title']) ?></strong><?php endif; ?>
                                    </p>
                                    <p class="muted mini-request__msg">“<?= e(excerpt($r['message'], 110)) ?>” · <?= e(time_ago($r['created_at'])) ?></p>
                                </div>
                                <div class="mini-request__actions">
                                    <form method="post" action="requests.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="return" value="dashboard">
                                        <button class="btn btn--primary btn--sm" type="submit"><?= icon('check', 15) ?> Accept</button>
                                    </form>
                                    <form method="post" action="requests.php" data-confirm="Decline this request?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="return" value="dashboard">
                                        <button class="btn btn--ghost btn--sm btn--danger-text" type="submit">Decline</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted card__empty">No pending requests right now. Adding more skills you can teach helps other students find you.</p>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><?= icon('zap', 18) ?> Suggested for you</h2>
                    <a class="link-arrow" href="skills.php?type=teach">Browse all <?= icon('arrow-right', 14) ?></a>
                </div>
                <?php if ($learnCount === 0): ?>
                    <p class="muted card__empty">Add skills you want to learn and we'll suggest students who can teach them.</p>
                    <a class="btn btn--primary btn--sm" href="skill_form.php?type=learn"><?= icon('plus', 15) ?> Add a skill to learn</a>
                <?php elseif (!$suggestions): ?>
                    <p class="muted card__empty">Nobody teaches those categories yet. Check back soon, or browse all skills.</p>
                <?php else: ?>
                    <ul class="suggestions">
                        <?php foreach ($suggestions as $s): ?>
                            <li class="suggestion">
                                <a href="profile.php?id=<?= (int) $s['user_id'] ?>"><?= avatar($s['owner_name'], $s['owner_avatar'], 'md') ?></a>
                                <div class="suggestion__body">
                                    <strong><?= e($s['title']) ?></strong>
                                    <small class="muted">
                                        <?= e($s['category_icon']) ?> <?= e($s['owner_name']) ?> · <?= e(SKILL_LEVELS[$s['level']]) ?>
                                    </small>
                                    <?php if ($s['mutual_match']): ?>
                                        <span class="badge badge--mutual"><?= icon('swap', 12) ?> Mutual match</span>
                                    <?php endif; ?>
                                </div>
                                <a class="btn btn--secondary btn--sm" href="request_create.php?skill_id=<?= (int) $s['id'] ?>">Request</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

        <aside class="dashboard-grid__side">
            <section class="card profile-mini">
                <div class="profile-mini__head">
                    <?= avatar($me['name'], $me['avatar'], 'lg') ?>
                    <div>
                        <strong><?= e($me['name']) ?></strong>
                        <small class="muted"><?= e($me['course'] ?: 'Add your course') ?></small>
                    </div>
                </div>
                <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $completeness['percent'] ?>" aria-label="Profile completeness">
                    <span style="width: <?= $completeness['percent'] ?>%"></span>
                </div>
                <p class="progress__label"><strong><?= $completeness['percent'] ?>%</strong> profile complete</p>
                <?php if ($completeness['missing']): ?>
                    <ul class="checklist">
                        <?php foreach (array_slice($completeness['missing'], 0, 3) as $item): ?>
                            <li><?= e($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a class="btn btn--secondary btn--sm btn--block" href="profile_edit.php">Complete profile</a>
                <?php else: ?>
                    <p class="muted">Your profile is all set. 🎉</p>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card__head">
                    <h2 class="card__title"><?= icon('grid', 18) ?> My skills</h2>
                    <a class="link-arrow" href="profile.php#skills">Manage <?= icon('arrow-right', 14) ?></a>
                </div>
                <?php if ($mySkills): ?>
                    <ul class="my-skills">
                        <?php foreach ($mySkills as $skill): ?>
                            <li>
                                <span class="dot dot--<?= e($skill['type']) ?>" title="<?= e(SKILL_TYPES[$skill['type']]) ?>"></span>
                                <a href="skill_form.php?id=<?= (int) $skill['id'] ?>"><?= e($skill['title']) ?></a>
                                <small class="muted"><?= e(SKILL_LEVELS[$skill['level']]) ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="legend"><span class="dot dot--teach"></span> Teach <span class="dot dot--learn"></span> Learn</p>
                <?php else: ?>
                    <p class="muted card__empty">You have not listed any skills yet.</p>
                    <a class="btn btn--primary btn--sm btn--block" href="skill_form.php"><?= icon('plus', 15) ?> Add your first skill</a>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
