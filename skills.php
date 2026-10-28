<?php
require __DIR__ . '/includes/init.php';

// --- Read and sanitise filters from the query string ---
$filters = [
    'q'        => mb_substr(get_str('q'), 0, 100),
    'category' => get_int('category'),
    'type'     => get_str('type'),
    'level'    => get_str('level'),
    'mode'     => get_str('mode'),
    'sort'     => get_str('sort'),
];
if (!isset(SKILL_TYPES[$filters['type']])) {
    $filters['type'] = '';
}
if (!isset(SKILL_LEVELS[$filters['level']])) {
    $filters['level'] = '';
}
if (!isset(SKILL_MODES[$filters['mode']])) {
    $filters['mode'] = '';
}
if (!category_exists($filters['category'])) {
    $filters['category'] = 0;
}
$sorts = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'az' => 'Name A–Z'];
if (!isset($sorts[$filters['sort']])) {
    $filters['sort'] = 'newest';
}

// --- Build the WHERE clause with placeholders only ---
$where = ["u.status = 'active'"];
$params = [];

if ($filters['q'] !== '') {
    // Escape LIKE wildcards so "%" or "_" typed by the user are matched literally.
    $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
    $where[] = '(s.title LIKE ? OR s.description LIKE ? OR c.name LIKE ? OR u.name LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}
if ($filters['category']) {
    $where[] = 's.category_id = ?';
    $params[] = $filters['category'];
}
if ($filters['type'] !== '') {
    $where[] = 's.type = ?';
    $params[] = $filters['type'];
}
if ($filters['level'] !== '') {
    $where[] = 's.level = ?';
    $params[] = $filters['level'];
}
if ($filters['mode'] !== '') {
    // "both" listings suit anyone looking for online or in-person.
    if ($filters['mode'] === 'both') {
        $where[] = "s.mode = 'both'";
    } else {
        $where[] = "(s.mode = ? OR s.mode = 'both')";
        $params[] = $filters['mode'];
    }
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$orderSql = [
    'newest' => ' ORDER BY s.created_at DESC, s.id DESC',
    'oldest' => ' ORDER BY s.created_at ASC, s.id ASC',
    'az'     => ' ORDER BY s.title ASC, s.id ASC',
][$filters['sort']];

$countStmt = db()->prepare(
    'SELECT COUNT(*) FROM skills s
     JOIN categories c ON c.id = s.category_id
     JOIN users u ON u.id = s.user_id' . $whereSql
);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pager = paginate($total, get_int('page', 1), SKILLS_PER_PAGE);

$stmt = db()->prepare(
    skill_select_sql() . $whereSql . $orderSql
    . ' LIMIT ' . (int) $pager['per_page'] . ' OFFSET ' . (int) $pager['offset']
);
$stmt->execute($params);
$skills = $stmt->fetchAll();

$hasFilters = $filters['q'] !== '' || $filters['category'] || $filters['type'] !== '' || $filters['level'] !== '' || $filters['mode'] !== '';

$pageTitle = 'Browse skills';
$activeNav = 'skills';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <h1>Browse skills</h1>
            <p class="muted">Find students who can teach you something, or who want to learn what you know.</p>
        </div>
        <?php if (is_logged_in()): ?>
            <a class="btn btn--primary" href="skill_form.php"><?= icon('plus', 16) ?> Add a skill</a>
        <?php endif; ?>
    </div>

    <form class="filters card" method="get" action="skills.php" role="search" data-autosubmit>
        <div class="filters__search">
            <?= icon('search', 18) ?>
            <label class="sr-only" for="q">Search skills</label>
            <input id="q" name="q" type="search" placeholder="Search by skill, topic, category or student name…"
                   value="<?= e($filters['q']) ?>" maxlength="100" autocomplete="off">
        </div>

        <div class="filters__row">
            <label class="sr-only" for="f-type">Listing type</label>
            <select id="f-type" name="type">
                <option value="">Teach &amp; learn</option>
                <option value="teach"<?= $filters['type'] === 'teach' ? ' selected' : '' ?>>Can teach</option>
                <option value="learn"<?= $filters['type'] === 'learn' ? ' selected' : '' ?>>Wants to learn</option>
            </select>

            <label class="sr-only" for="f-category">Category</label>
            <select id="f-category" name="category">
                <option value="">All categories</option>
                <?php foreach (categories() as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"<?= $filters['category'] === (int) $category['id'] ? ' selected' : '' ?>>
                        <?= e($category['icon'] . ' ' . $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="sr-only" for="f-level">Level</label>
            <select id="f-level" name="level">
                <option value="">Any level</option>
                <?php foreach (SKILL_LEVELS as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filters['level'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="sr-only" for="f-mode">Meeting mode</label>
            <select id="f-mode" name="mode">
                <option value="">Online or in person</option>
                <option value="online"<?= $filters['mode'] === 'online' ? ' selected' : '' ?>>Online</option>
                <option value="offline"<?= $filters['mode'] === 'offline' ? ' selected' : '' ?>>In person</option>
                <option value="both"<?= $filters['mode'] === 'both' ? ' selected' : '' ?>>Flexible (both)</option>
            </select>

            <label class="sr-only" for="f-sort">Sort by</label>
            <select id="f-sort" name="sort">
                <?php foreach ($sorts as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $filters['sort'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn btn--primary filters__submit" type="submit"><?= icon('search', 16) ?> Search</button>
        </div>
    </form>

    <div class="results-bar">
        <p class="muted" aria-live="polite">
            <?php if ($total === 0): ?>
                No skills found
            <?php else: ?>
                Showing <strong><?= $pager['offset'] + 1 ?>–<?= $pager['offset'] + count($skills) ?></strong> of <strong><?= $total ?></strong> skill<?= $total === 1 ? '' : 's' ?>
            <?php endif; ?>
            <?php if ($filters['q'] !== ''): ?> for “<?= e($filters['q']) ?>”<?php endif; ?>
        </p>
        <?php if ($hasFilters): ?>
            <a class="btn btn--ghost btn--sm" href="skills.php"><?= icon('x', 14) ?> Clear filters</a>
        <?php endif; ?>
    </div>

    <?php if ($skills): ?>
        <div class="card-grid">
            <?php foreach ($skills as $skill) { skill_card($skill); } ?>
        </div>
        <?php pagination($pager); ?>
    <?php else: ?>
        <?php empty_state('search', 'No matching skills',
            $hasFilters ? 'Try a different keyword or remove a filter.' : 'Nobody has listed a skill yet. Be the first!',
            $hasFilters ? '<a class="btn btn--secondary" href="skills.php">Clear filters</a>'
                        : '<a class="btn btn--primary" href="skill_form.php">Add a skill</a>'); ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
