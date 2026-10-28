<?php
require __DIR__ . '/includes/init.php';
require_admin();

$pdo = db();

$users = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(role = 'admin') AS admins,
            SUM(status = 'suspended') AS suspended,
            SUM(created_at > (NOW() - INTERVAL 7 DAY)) AS new_week
     FROM users"
)->fetch();
$skills = $pdo->query("SELECT COUNT(*) AS total, SUM(type = 'teach') AS teach, SUM(type = 'learn') AS learn FROM skills")->fetch();
$requests = $pdo->query(
    "SELECT COUNT(*) AS total, SUM(status = 'pending') AS pending, SUM(status = 'accepted') AS accepted, SUM(status = 'completed') AS completed
     FROM exchange_requests"
)->fetch();

$audit = $pdo->query(
    'SELECT admin_name, action, target_type, target_id, detail, created_at FROM admin_audit ORDER BY id DESC LIMIT 15'
)->fetchAll();

$actionLabels = [
    'suspend_user'  => 'Suspended user',
    'activate_user' => 'Reactivated user',
    'promote_user'  => 'Made user an admin',
    'demote_user'   => 'Removed admin role',
    'delete_user'   => 'Deleted user',
    'delete_skill'  => 'Deleted skill listing',
];

$pageTitle = 'Admin';
$activeNav = 'admin';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <h1>Admin overview</h1>
            <p class="muted">Manage students and keep the skill listings appropriate.</p>
        </div>
        <div class="page-head__actions">
            <a class="btn btn--secondary" href="admin_skills.php"><?= icon('grid', 16) ?> Moderate skills</a>
            <a class="btn btn--primary" href="admin_users.php"><?= icon('users', 16) ?> Manage users</a>
        </div>
    </div>

    <section class="stats-grid" aria-label="Platform numbers">
        <a class="stat-card" href="admin_users.php">
            <span class="stat-card__icon stat-card__icon--teach"><?= icon('users', 20) ?></span>
            <span class="stat-card__value"><?= (int) $users['total'] ?></span>
            <span class="stat-card__label">Accounts (<?= (int) $users['admins'] ?> admin<?= (int) $users['admins'] === 1 ? '' : 's' ?>)</span>
        </a>
        <a class="stat-card" href="admin_users.php?status=suspended">
            <span class="stat-card__icon stat-card__icon--pending"><?= icon('lock', 20) ?></span>
            <span class="stat-card__value"><?= (int) $users['suspended'] ?></span>
            <span class="stat-card__label">Suspended accounts</span>
        </a>
        <a class="stat-card" href="admin_users.php">
            <span class="stat-card__icon stat-card__icon--active"><?= icon('user', 20) ?></span>
            <span class="stat-card__value"><?= (int) $users['new_week'] ?></span>
            <span class="stat-card__label">New in the last 7 days</span>
        </a>
        <a class="stat-card" href="admin_skills.php">
            <span class="stat-card__icon stat-card__icon--learn"><?= icon('grid', 20) ?></span>
            <span class="stat-card__value"><?= (int) $skills['total'] ?></span>
            <span class="stat-card__label">Skill listings (<?= (int) $skills['teach'] ?> teach · <?= (int) $skills['learn'] ?> learn)</span>
        </a>
        <div class="stat-card">
            <span class="stat-card__icon stat-card__icon--done"><?= icon('swap', 20) ?></span>
            <span class="stat-card__value"><?= (int) $requests['total'] ?></span>
            <span class="stat-card__label">Exchange requests (<?= (int) $requests['pending'] ?> pending · <?= (int) $requests['completed'] ?> completed)</span>
        </div>
    </section>

    <section class="card">
        <div class="card__head">
            <h2 class="card__title"><?= icon('clock', 18) ?> Recent admin activity</h2>
        </div>
        <?php if ($audit): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Details</th></tr></thead>
                    <tbody>
                    <?php foreach ($audit as $row): ?>
                        <tr>
                            <td class="nowrap" title="<?= e($row['created_at']) ?>"><?= e(time_ago($row['created_at'])) ?></td>
                            <td><?= e($row['admin_name']) ?></td>
                            <td><?= e($actionLabels[$row['action']] ?? $row['action']) ?></td>
                            <td class="muted"><?= e($row['detail']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted card__empty">No admin actions yet. Suspending, promoting or deleting something will be recorded here.</p>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
