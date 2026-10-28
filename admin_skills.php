<?php
require __DIR__ . '/includes/init.php';
require_admin();

$pdo = db();

function skills_return_url(string $queryString): string
{
    parse_str($queryString, $params);
    $keep = [];
    foreach (['q', 'type', 'page'] as $key) {
        if (isset($params[$key]) && is_string($params[$key]) && $params[$key] !== '') {
            $keep[$key] = $params[$key];
        }
    }
    return 'admin_skills.php' . ($keep ? '?' . http_build_query($keep) : '');
}

// ------------------------------------------------------------
//  Remove a listing (POST only, CSRF protected, admin only)
// ------------------------------------------------------------
if (is_post()) {
    verify_csrf();

    $returnTo = skills_return_url(post_str('return_qs'));
    $stmt = $pdo->prepare('SELECT s.id, s.title, u.name AS owner_name FROM skills s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $stmt->execute([post_int('skill_id')]);
    $skill = $stmt->fetch();

    if (!$skill) {
        flash('error', 'That listing no longer exists.');
        redirect($returnTo);
    }

    $pdo->prepare('DELETE FROM skills WHERE id = ?')->execute([$skill['id']]);
    audit_log('delete_skill', 'skill', (int) $skill['id'], '"' . $skill['title'] . '" by ' . $skill['owner_name']);
    flash('success', '“' . $skill['title'] . '” by ' . $skill['owner_name'] . ' was removed.');
    redirect($returnTo);
}

// ------------------------------------------------------------
//  Listing
// ------------------------------------------------------------
$filters = [
    'q'    => mb_substr(get_str('q'), 0, 100),
    'type' => isset(SKILL_TYPES[get_str('type')]) ? get_str('type') : '',
];

$where = [];
$params = [];
if ($filters['q'] !== '') {
    $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
    $where[] = '(s.title LIKE ? OR s.description LIKE ? OR u.name LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($filters['type'] !== '') {
    $where[] = 's.type = ?';
    $params[] = $filters['type'];
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare('SELECT COUNT(*) FROM skills s JOIN users u ON u.id = s.user_id' . $whereSql);
$stmt->execute($params);
$pager = paginate((int) $stmt->fetchColumn(), get_int('page', 1), 15);

$stmt = $pdo->prepare(
    skill_select_sql() . $whereSql . ' ORDER BY s.created_at DESC, s.id DESC LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$returnQs = http_build_query(array_filter($filters + ['page' => $pager['page'] > 1 ? $pager['page'] : ''], fn ($v) => $v !== ''));

$pageTitle = 'Moderate skills';
$activeNav = 'admin';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <a class="back-link" href="admin.php"><?= icon('chevron-left', 16) ?> Admin overview</a>
            <h1>Moderate skill listings</h1>
            <p class="muted"><?= $pager['total'] ?> listing<?= $pager['total'] === 1 ? '' : 's' ?><?= $where ? ' match your filters' : '' ?>. Remove anything inappropriate or misleading.</p>
        </div>
    </div>

    <form class="filters card" method="get" action="admin_skills.php" role="search">
        <div class="filters__search">
            <?= icon('search', 18) ?>
            <label class="sr-only" for="q">Search listings</label>
            <input id="q" name="q" type="search" placeholder="Search by skill, description or student…" value="<?= e($filters['q']) ?>" maxlength="100" autocomplete="off">
        </div>
        <div class="filters__row filters__row--admin">
            <label class="sr-only" for="f-type">Type</label>
            <select id="f-type" name="type">
                <option value="">Teach &amp; learn</option>
                <option value="teach"<?= $filters['type'] === 'teach' ? ' selected' : '' ?>>Can teach</option>
                <option value="learn"<?= $filters['type'] === 'learn' ? ' selected' : '' ?>>Wants to learn</option>
            </select>
            <button class="btn btn--primary" type="submit"><?= icon('search', 16) ?> Search</button>
            <?php if ($where): ?><a class="btn btn--ghost" href="admin_skills.php"><?= icon('x', 14) ?> Clear</a><?php endif; ?>
        </div>
    </form>

    <?php if ($rows): ?>
        <div class="card table-card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Skill</th><th>Type</th><th>Category</th><th>Student</th><th>Added</th><th class="actions-col">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['title']) ?></strong>
                                <?php if ($row['description']): ?><small class="muted block"><?= e(excerpt($row['description'], 90)) ?></small><?php endif; ?>
                            </td>
                            <td><?= type_badge($row['type']) ?></td>
                            <td class="nowrap"><?= e($row['category_icon'] . ' ' . $row['category_name']) ?></td>
                            <td><a href="profile.php?id=<?= (int) $row['user_id'] ?>"><?= e($row['owner_name']) ?></a><?= $row['owner_status'] !== 'active' ? ' <span class="badge badge--rejected">Suspended</span>' : '' ?></td>
                            <td class="nowrap" title="<?= e($row['created_at']) ?>"><?= e(time_ago($row['created_at'])) ?></td>
                            <td class="actions-col">
                                <form method="post" action="admin_skills.php" data-confirm="Remove “<?= e($row['title']) ?>” by <?= e($row['owner_name']) ?>? Any exchange requests for it will also be removed.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="skill_id" value="<?= (int) $row['id'] ?>">
                                    <input type="hidden" name="return_qs" value="<?= e($returnQs) ?>">
                                    <button class="btn btn--sm btn--ghost btn--danger-text" type="submit"><?= icon('trash', 15) ?> Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php pagination($pager); ?>
    <?php else: ?>
        <?php empty_state('search', 'No listings found', 'Try a different search or clear the filters.', '<a class="btn btn--secondary" href="admin_skills.php">Clear filters</a>'); ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
