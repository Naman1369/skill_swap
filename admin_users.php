<?php
require __DIR__ . '/includes/init.php';
require_admin();

$me = current_user();
$pdo = db();

/** Rebuild a safe return URL that keeps the admin's current filters. */
function users_return_url(string $queryString): string
{
    parse_str($queryString, $params);
    $keep = [];
    foreach (['q', 'role', 'status', 'page'] as $key) {
        if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '') {
            $keep[$key] = $params[$key];
        }
    }
    return 'admin_users.php' . ($keep ? '?' . http_build_query($keep) : '');
}

// ------------------------------------------------------------
//  Actions (POST only, CSRF protected, admin only)
// ------------------------------------------------------------
if (is_post()) {
    verify_csrf();

    $returnTo = users_return_url(post_str('return_qs'));
    $targetId = post_int('user_id');
    $action = post_str('action');

    $stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.role, u.status, p.avatar FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch();

    if (!$target) {
        flash('error', 'That account no longer exists.');
        redirect($returnTo);
    }
    if ((int) $target['id'] === (int) $me['id']) {
        flash('error', 'You cannot change your own account from the admin area.');
        redirect($returnTo);
    }
    // Admins must be demoted first, so one admin cannot suspend or delete another in a single click.
    if ($target['role'] === ROLE_ADMIN && in_array($action, ['suspend', 'delete'], true)) {
        flash('error', 'Remove the admin role from ' . $target['name'] . ' first, then try again.');
        redirect($returnTo);
    }

    $label = $target['name'] . ' <' . $target['email'] . '>';
    switch ($action) {
        case 'suspend':
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$target['id']]);
            audit_log('suspend_user', 'user', (int) $target['id'], $label);
            flash('success', $target['name'] . ' has been suspended and logged out.');
            break;
        case 'activate':
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$target['id']]);
            audit_log('activate_user', 'user', (int) $target['id'], $label);
            flash('success', $target['name'] . ' can use SkillSwap again.');
            break;
        case 'promote':
            $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$target['id']]);
            audit_log('promote_user', 'user', (int) $target['id'], $label);
            flash('success', $target['name'] . ' is now an admin.');
            break;
        case 'demote':
            $pdo->prepare("UPDATE users SET role = 'student' WHERE id = ?")->execute([$target['id']]);
            audit_log('demote_user', 'user', (int) $target['id'], $label);
            flash('success', $target['name'] . ' is now a regular student.');
            break;
        case 'delete':
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$target['id']]);
            if ($target['avatar'] && avatar_url($target['avatar']) !== null) {
                @unlink(AVATAR_DIR . basename($target['avatar']));
            }
            audit_log('delete_user', 'user', (int) $target['id'], $label);
            flash('success', 'The account of ' . $target['name'] . ' and all their data were deleted.');
            break;
        default:
            abort(400, 'Unknown action.');
    }
    redirect($returnTo);
}

// ------------------------------------------------------------
//  Listing
// ------------------------------------------------------------
$filters = [
    'q'      => mb_substr(get_str('q'), 0, 100),
    'role'   => in_array(get_str('role'), [ROLE_STUDENT, ROLE_ADMIN], true) ? get_str('role') : '',
    'status' => in_array(get_str('status'), ['active', 'suspended'], true) ? get_str('status') : '',
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
    $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
    array_push($params, $like, $like);
}
if ($filters['role'] !== '') {
    $where[] = 'u.role = ?';
    $params[] = $filters['role'];
}
if ($filters['status'] !== '') {
    $where[] = 'u.status = ?';
    $params[] = $filters['status'];
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
$stmt->execute($params);
$pager = paginate((int) $stmt->fetchColumn(), get_int('page', 1), 15);

$stmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, p.avatar,
            (SELECT COUNT(*) FROM skills s WHERE s.user_id = u.id) AS skill_count,
            (SELECT COUNT(*) FROM exchange_requests r WHERE r.sender_id = u.id OR r.receiver_id = u.id) AS request_count
     FROM users u LEFT JOIN profiles p ON p.user_id = u.id' . $whereSql
    . ' ORDER BY u.created_at DESC, u.id DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$returnQs = http_build_query(array_filter($filters + ['page' => $pager['page'] > 1 ? $pager['page'] : ''], fn ($v) => $v !== ''));

$pageTitle = 'Manage users';
$activeNav = 'admin';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <a class="back-link" href="admin.php"><?= icon('chevron-left', 16) ?> Admin overview</a>
            <h1>Manage users</h1>
            <p class="muted"><?= $pager['total'] ?> account<?= $pager['total'] === 1 ? '' : 's' ?><?= $where ? ' match your filters' : '' ?>.</p>
        </div>
    </div>

    <form class="filters card" method="get" action="admin_users.php" role="search">
        <div class="filters__search">
            <?= icon('search', 18) ?>
            <label class="sr-only" for="q">Search users</label>
            <input id="q" name="q" type="search" placeholder="Search by name or email…" value="<?= e($filters['q']) ?>" maxlength="100" autocomplete="off">
        </div>
        <div class="filters__row filters__row--admin">
            <label class="sr-only" for="f-role">Role</label>
            <select id="f-role" name="role">
                <option value="">All roles</option>
                <option value="student"<?= $filters['role'] === 'student' ? ' selected' : '' ?>>Students</option>
                <option value="admin"<?= $filters['role'] === 'admin' ? ' selected' : '' ?>>Admins</option>
            </select>
            <label class="sr-only" for="f-status">Status</label>
            <select id="f-status" name="status">
                <option value="">Any status</option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="suspended"<?= $filters['status'] === 'suspended' ? ' selected' : '' ?>>Suspended</option>
            </select>
            <button class="btn btn--primary" type="submit"><?= icon('search', 16) ?> Search</button>
            <?php if ($where): ?><a class="btn btn--ghost" href="admin_users.php"><?= icon('x', 14) ?> Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if ($rows): ?>
        <div class="card table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>User</th><th>Role</th><th>Status</th><th class="num">Skills</th><th class="num">Requests</th><th>Joined</th><th class="actions-col">Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $isSelf = (int) $row['id'] === (int) $me['id']; $isAdminRow = $row['role'] === ROLE_ADMIN; ?>
                        <tr>
                            <td>
                                <a class="table-user" href="profile.php?id=<?= (int) $row['id'] ?>">
                                    <?= avatar($row['name'], $row['avatar'], 'sm') ?>
                                    <span><strong><?= e($row['name']) ?><?= $isSelf ? ' (you)' : '' ?></strong><small class="muted"><?= e($row['email']) ?></small></span>
                                </a>
                            </td>
                            <td><?= $isAdminRow ? '<span class="badge badge--mutual">Admin</span>' : '<span class="badge badge--muted">Student</span>' ?></td>
                            <td><?= $row['status'] === 'active' ? '<span class="badge badge--completed">Active</span>' : '<span class="badge badge--rejected">Suspended</span>' ?></td>
                            <td class="num"><?= (int) $row['skill_count'] ?></td>
                            <td class="num"><?= (int) $row['request_count'] ?></td>
                            <td class="nowrap" title="<?= e($row['created_at']) ?>"><?= e(date('j M Y', strtotime($row['created_at']))) ?></td>
                            <td class="actions-col">
                                <?php if ($isSelf): ?>
                                    <span class="muted">—</span>
                                <?php else: ?>
                                    <?php
                                    $buttons = [];
                                    if ($row['status'] === 'active' && !$isAdminRow) {
                                        $buttons[] = ['suspend', 'Suspend', 'btn--ghost btn--danger-text', "Suspend {$row['name']}? They will be logged out and hidden from other students."];
                                    } elseif ($row['status'] !== 'active') {
                                        $buttons[] = ['activate', 'Reactivate', 'btn--secondary', ''];
                                    }
                                    $buttons[] = $isAdminRow
                                        ? ['demote', 'Remove admin', 'btn--ghost', "Remove the admin role from {$row['name']}?"]
                                        : ['promote', 'Make admin', 'btn--ghost', "Make {$row['name']} an admin? They will be able to manage all users and listings."];
                                    if (!$isAdminRow) {
                                        $buttons[] = ['delete', 'Delete', 'btn--ghost btn--danger-text', "Permanently delete {$row['name']} and all their skills and requests? This cannot be undone."];
                                    }
                                    ?>
                                    <div class="row-actions">
                                        <?php foreach ($buttons as [$act, $text, $class, $confirm]): ?>
                                            <form method="post" action="admin_users.php"<?= $confirm ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                                <input type="hidden" name="action" value="<?= e($act) ?>">
                                                <input type="hidden" name="return_qs" value="<?= e($returnQs) ?>">
                                                <button class="btn btn--sm <?= $class ?>" type="submit"><?= e($text) ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php pagination($pager); ?>
    <?php else: ?>
        <?php empty_state('users', 'No users found', 'Try a different search or clear the filters.', '<a class="btn btn--secondary" href="admin_users.php">Clear filters</a>'); ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
