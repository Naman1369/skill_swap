<?php
/**
 * Small reusable pieces of HTML shared by several pages.
 */

/** Inline SVG icon (stroke style, inherits the text colour). */
function icon(string $name, int $size = 18): string
{
    static $paths = [
        'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'check'        => '<polyline points="20 6 9 17 4 12"/>',
        'edit'         => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'phone'        => '<rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>',
        'link'         => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'sun'          => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon'         => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'menu'         => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'arrow-right'  => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'swap'         => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'inbox'        => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'send'         => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'map-pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'globe'        => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'book'         => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'award'        => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
        'zap'          => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'grid'         => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'eye'          => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'      => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'info'         => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'alert'        => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'home'         => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right'=> '<polyline points="9 18 15 12 9 6"/>',
        'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ];

    $body = $paths[$name] ?? $paths['info'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/** Profile photo, or coloured initials when no photo was uploaded. */
function avatar(string $name, ?string $file, string $size = 'md'): string
{
    $url = avatar_url($file);
    if ($url !== null) {
        return '<img class="avatar avatar--' . e($size) . '" src="' . e($url) . '" alt="' . e($name) . '" loading="lazy">';
    }
    $tone = abs(crc32($name)) % 6;
    return '<span class="avatar avatar--' . e($size) . ' avatar--tone' . $tone . '" role="img" aria-label="' . e($name) . '">'
        . e(initials($name)) . '</span>';
}

function status_badge(string $status): string
{
    $label = REQUEST_STATUSES[$status] ?? ucfirst($status);
    return '<span class="badge badge--' . e($status) . '">' . e($label) . '</span>';
}

function type_badge(string $type): string
{
    $label = $type === 'teach' ? 'Teaches' : 'Wants to learn';
    return '<span class="badge badge--' . e($type) . '">' . e($label) . '</span>';
}

function field_error(array $errors, string $field): string
{
    if (empty($errors[$field])) {
        return '';
    }
    return '<p class="field-error" id="' . e($field) . '-error">' . e($errors[$field]) . '</p>';
}

/** Attributes that mark an input invalid when the server rejected it. */
function invalid_attr(array $errors, string $field): string
{
    return empty($errors[$field]) ? '' : ' class="is-invalid" aria-invalid="true" aria-describedby="' . e($field) . '-error"';
}

function empty_state(string $iconName, string $title, string $text, string $actionHtml = ''): void
{
    ?>
    <div class="empty-state">
        <div class="empty-state__icon"><?= icon($iconName, 28) ?></div>
        <h3><?= e($title) ?></h3>
        <p><?= e($text) ?></p>
        <?= $actionHtml ?>
    </div>
    <?php
}

/**
 * A skill listing card.
 * Options: show_owner (bool, default true)
 */
function skill_card(array $skill, array $options = []): void
{
    $showOwner = $options['show_owner'] ?? true;
    $viewerId = current_user_id();
    $isOwner = $viewerId !== null && $viewerId === (int) $skill['user_id'];
    ?>
    <article class="skill-card skill-card--<?= e($skill['type']) ?>">
        <div class="skill-card__top">
            <span class="chip chip--muted"><span aria-hidden="true"><?= e($skill['category_icon']) ?></span> <?= e($skill['category_name']) ?></span>
            <?= type_badge($skill['type']) ?>
        </div>
        <h3 class="skill-card__title"><?= e($skill['title']) ?></h3>
        <?php if (!empty($skill['description'])): ?>
            <p class="skill-card__desc"><?= e(excerpt($skill['description'], 130)) ?></p>
        <?php endif; ?>
        <ul class="skill-card__meta">
            <li><?= icon('award', 14) ?> <?= e(SKILL_LEVELS[$skill['level']] ?? $skill['level']) ?></li>
            <li><?= icon($skill['mode'] === 'offline' ? 'map-pin' : 'globe', 14) ?> <?= e(SKILL_MODES[$skill['mode']] ?? $skill['mode']) ?></li>
        </ul>
        <div class="skill-card__footer">
            <?php if ($showOwner): ?>
                <a class="skill-card__owner" href="profile.php?id=<?= (int) $skill['user_id'] ?>">
                    <?= avatar($skill['owner_name'], $skill['owner_avatar'] ?? null, 'sm') ?>
                    <span>
                        <strong><?= e($skill['owner_name']) ?><?= $isOwner ? ' (you)' : '' ?></strong>
                        <?php if (!empty($skill['owner_course'])): ?><small><?= e($skill['owner_course']) ?></small><?php endif; ?>
                    </span>
                </a>
            <?php endif; ?>
            <div class="skill-card__actions">
                <?php if ($isOwner): ?>
                    <a class="btn btn--ghost btn--sm" href="skill_form.php?id=<?= (int) $skill['id'] ?>"><?= icon('edit', 15) ?> Edit</a>
                    <form method="post" action="skill_delete.php" data-confirm="Delete “<?= e($skill['title']) ?>”? Any exchange requests for this skill will also be removed.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $skill['id'] ?>">
                        <button class="btn btn--ghost btn--sm btn--danger-text" type="submit"><?= icon('trash', 15) ?> Delete</button>
                    </form>
                <?php elseif ($skill['type'] === 'teach'): ?>
                    <a class="btn btn--primary btn--sm" href="request_create.php?skill_id=<?= (int) $skill['id'] ?>"><?= icon('swap', 15) ?> Request swap</a>
                <?php else: ?>
                    <a class="btn btn--secondary btn--sm" href="profile.php?id=<?= (int) $skill['user_id'] ?>">View profile</a>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

function pagination(array $p): void
{
    if ($p['pages'] <= 1) {
        return;
    }
    $page = $p['page'];
    $window = array_unique(array_filter(
        [1, $page - 1, $page, $page + 1, $p['pages']],
        fn ($n) => $n >= 1 && $n <= $p['pages']
    ));
    sort($window);
    ?>
    <nav class="pagination" aria-label="Pagination">
        <?php if ($page > 1): ?>
            <a class="pagination__link" href="<?= e(query_with(['page' => $page - 1])) ?>" aria-label="Previous page"><?= icon('chevron-left', 16) ?></a>
        <?php endif; ?>
        <?php $prev = 0; foreach ($window as $n): ?>
            <?php if ($n - $prev > 1): ?><span class="pagination__gap">…</span><?php endif; ?>
            <?php if ($n === $page): ?>
                <span class="pagination__link is-current" aria-current="page"><?= $n ?></span>
            <?php else: ?>
                <a class="pagination__link" href="<?= e(query_with(['page' => $n])) ?>"><?= $n ?></a>
            <?php endif; ?>
        <?php $prev = $n; endforeach; ?>
        <?php if ($page < $p['pages']): ?>
            <a class="pagination__link" href="<?= e(query_with(['page' => $page + 1])) ?>" aria-label="Next page"><?= icon('chevron-right', 16) ?></a>
        <?php endif; ?>
    </nav>
    <?php
}
