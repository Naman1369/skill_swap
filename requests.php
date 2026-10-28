<?php
require __DIR__ . '/includes/init.php';
require_login();

$me = current_user();
$myId = (int) $me['id'];

// ------------------------------------------------------------
//  Status changes (accept / decline / cancel / complete)
// ------------------------------------------------------------
if (is_post()) {
    verify_csrf();

    $requestId = post_int('request_id');
    $action = post_str('action');
    $returnTab = post_str('tab') === 'outgoing' ? 'outgoing' : 'incoming';
    $returnTo = post_str('return') === 'dashboard' ? 'dashboard.php' : 'requests.php?tab=' . $returnTab;

    $stmt = db()->prepare(
        'SELECT r.*, s.title AS skill_title, su.name AS sender_name, ru.name AS receiver_name
         FROM exchange_requests r
         JOIN skills s ON s.id = r.skill_id
         JOIN users su ON su.id = r.sender_id
         JOIN users ru ON ru.id = r.receiver_id
         WHERE r.id = ? AND (r.sender_id = ? OR r.receiver_id = ?)'
    );
    $stmt->execute([$requestId, $myId, $myId]);
    $request = $stmt->fetch();

    if (!$request) {
        abort(404, 'That exchange request could not be found.');
    }

    $isReceiver = (int) $request['receiver_id'] === $myId;
    $isSender = (int) $request['sender_id'] === $myId;

    // action => [required current status, new status, who may do it]
    $transitions = [
        'accept'   => ['pending',  'accepted',  $isReceiver],
        'reject'   => ['pending',  'rejected',  $isReceiver],
        'cancel'   => ['pending',  'cancelled', $isSender],
        'complete' => ['accepted', 'completed', $isReceiver || $isSender],
    ];

    if (!isset($transitions[$action])) {
        abort(400, 'Unknown action.');
    }
    [$from, $to, $allowed] = $transitions[$action];

    if (!$allowed || $request['status'] !== $from) {
        $verbs = ['accept' => 'accepted', 'reject' => 'declined', 'cancel' => 'cancelled', 'complete' => 'completed'];
        flash('error', 'This request cannot be ' . $verbs[$action] . ' by you right now. It is currently ' . strtolower(REQUEST_STATUSES[$request['status']]) . '.');
        redirect($returnTo);
    }

    // The status condition protects against double submissions.
    $update = db()->prepare('UPDATE exchange_requests SET status = ? WHERE id = ? AND status = ?');
    $update->execute([$to, $request['id'], $from]);

    $other = $isReceiver ? first_name($request['sender_name']) : first_name($request['receiver_name']);
    $messages = [
        'accept'   => "You accepted {$other}'s request. Your contact details are now shared with each other.",
        'reject'   => "You declined {$other}'s request.",
        'cancel'   => 'Your request has been cancelled.',
        'complete' => "Exchange with {$other} marked as completed. Nice work!",
    ];
    flash('success', $messages[$action]);
    redirect($returnTo);
}

// ------------------------------------------------------------
//  Listing
// ------------------------------------------------------------
$tab = get_str('tab') === 'outgoing' ? 'outgoing' : 'incoming';
$status = get_str('status');
if (!isset(REQUEST_STATUSES[$status])) {
    $status = '';
}

$meCol = $tab === 'incoming' ? 'r.receiver_id' : 'r.sender_id';
$otherCol = $tab === 'incoming' ? 'r.sender_id' : 'r.receiver_id';

$sql = "SELECT r.id, r.status, r.message, r.created_at, r.updated_at,
               s.id AS skill_id, s.title AS skill_title,
               os.title AS offered_title,
               o.id AS other_id, o.name AS other_name, o.email AS other_email,
               op.avatar AS other_avatar, op.course AS other_course, op.phone AS other_phone, op.contact_link AS other_link
        FROM exchange_requests r
        JOIN skills s ON s.id = r.skill_id
        LEFT JOIN skills os ON os.id = r.offered_skill_id
        JOIN users o ON o.id = {$otherCol}
        LEFT JOIN profiles op ON op.user_id = o.id
        WHERE {$meCol} = ?";
$params = [$myId];
if ($status !== '') {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
$sql .= " ORDER BY FIELD(r.status, 'pending', 'accepted', 'completed', 'rejected', 'cancelled'), r.updated_at DESC, r.id DESC";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT
        SUM(receiver_id = ? AND status = 'pending') AS incoming_pending,
        SUM(sender_id = ? AND status = 'pending')   AS outgoing_pending
     FROM exchange_requests"
);
$stmt->execute([$myId, $myId]);
$counts = $stmt->fetch();

$pageTitle = 'Exchange requests';
$activeNav = 'requests';
require __DIR__ . '/includes/header.php';
?>
<div class="container page">
    <div class="page-head">
        <div>
            <h1>Exchange requests</h1>
            <p class="muted">Respond to students who want to learn from you, and track the requests you have sent.</p>
        </div>
        <a class="btn btn--secondary" href="skills.php"><?= icon('search', 16) ?> Find skills</a>
    </div>

    <div class="tabs-bar">
        <nav class="tabs" aria-label="Request direction">
            <a class="tabs__tab<?= $tab === 'incoming' ? ' is-active' : '' ?>" href="requests.php?tab=incoming"<?= $tab === 'incoming' ? ' aria-current="page"' : '' ?>>
                <?= icon('inbox', 16) ?> Received
                <?php if ((int) $counts['incoming_pending'] > 0): ?><span class="nav-badge"><?= (int) $counts['incoming_pending'] ?></span><?php endif; ?>
            </a>
            <a class="tabs__tab<?= $tab === 'outgoing' ? ' is-active' : '' ?>" href="requests.php?tab=outgoing"<?= $tab === 'outgoing' ? ' aria-current="page"' : '' ?>>
                <?= icon('send', 16) ?> Sent
                <?php if ((int) $counts['outgoing_pending'] > 0): ?><span class="nav-badge nav-badge--muted"><?= (int) $counts['outgoing_pending'] ?></span><?php endif; ?>
            </a>
        </nav>

        <div class="status-filter" role="group" aria-label="Filter by status">
            <a class="chip<?= $status === '' ? ' is-active' : '' ?>" href="requests.php?tab=<?= $tab ?>">All</a>
            <?php foreach (REQUEST_STATUSES as $key => $label): ?>
                <a class="chip<?= $status === $key ? ' is-active' : '' ?>" href="requests.php?tab=<?= $tab ?>&amp;status=<?= e($key) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($requests): ?>
        <div class="request-list">
            <?php foreach ($requests as $r): ?>
                <?php $shared = in_array($r['status'], ['accepted', 'completed'], true); ?>
                <article class="request-card card request-card--<?= e($r['status']) ?>">
                    <header class="request-card__head">
                        <a class="request-card__person" href="profile.php?id=<?= (int) $r['other_id'] ?>">
                            <?= avatar($r['other_name'], $r['other_avatar'], 'md') ?>
                            <span>
                                <strong><?= e($r['other_name']) ?></strong>
                                <?php if ($r['other_course']): ?><small><?= e($r['other_course']) ?></small><?php endif; ?>
                            </span>
                        </a>
                        <div class="request-card__status">
                            <?= status_badge($r['status']) ?>
                            <small class="muted" title="<?= e($r['created_at']) ?>"><?= e(time_ago($r['created_at'])) ?></small>
                        </div>
                    </header>

                    <div class="request-card__swap">
                        <div class="swap-item swap-item--learn">
                            <small><?= $tab === 'incoming' ? 'Wants to learn from you' : 'You want to learn' ?></small>
                            <strong><?= e($r['skill_title']) ?></strong>
                        </div>
                        <span class="swap-arrow" aria-hidden="true"><?= icon('swap', 18) ?></span>
                        <div class="swap-item swap-item--teach">
                            <small><?= $tab === 'incoming' ? 'Offers to teach you' : 'You offered' ?></small>
                            <strong><?= $r['offered_title'] ? e($r['offered_title']) : '<span class="muted">Nothing specific</span>' ?></strong>
                        </div>
                    </div>

                    <blockquote class="request-card__message"><?= nl2br(e($r['message'])) ?></blockquote>

                    <?php if ($shared): ?>
                        <div class="request-card__contact">
                            <strong><?= icon('check-circle', 16) ?> Contact <?= e(first_name($r['other_name'])) ?> to plan your sessions</strong>
                            <ul class="contact-list contact-list--inline">
                                <li><?= icon('mail', 15) ?> <a href="mailto:<?= e($r['other_email']) ?>"><?= e($r['other_email']) ?></a></li>
                                <?php if ($r['other_phone']): ?>
                                    <li><?= icon('phone', 15) ?> <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $r['other_phone'])) ?>"><?= e($r['other_phone']) ?></a></li>
                                <?php endif; ?>
                                <?php if ($r['other_link']): ?>
                                    <li><?= icon('link', 15) ?> <a href="<?= e($r['other_link']) ?>" target="_blank" rel="noopener noreferrer nofollow">Profile link</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php
                    $actions = [];
                    if ($r['status'] === 'pending' && $tab === 'incoming') {
                        $actions[] = ['accept', 'Accept', 'btn--primary', 'check', ''];
                        $actions[] = ['reject', 'Decline', 'btn--ghost btn--danger-text', 'x', 'Decline this request?'];
                    } elseif ($r['status'] === 'pending' && $tab === 'outgoing') {
                        $actions[] = ['cancel', 'Cancel request', 'btn--ghost btn--danger-text', 'x', 'Cancel this request?'];
                    } elseif ($r['status'] === 'accepted') {
                        $actions[] = ['complete', 'Mark as completed', 'btn--secondary', 'award', 'Mark this exchange as completed?'];
                    }
                    ?>
                    <?php if ($actions): ?>
                        <footer class="request-card__actions">
                            <?php foreach ($actions as [$action, $label, $class, $iconName, $confirm]): ?>
                                <form method="post" action="requests.php"<?= $confirm ? ' data-confirm="' . e($confirm) . '"' : '' ?>>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="request_id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="action" value="<?= e($action) ?>">
                                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                                    <button class="btn btn--sm <?= $class ?>" type="submit"><?= icon($iconName, 15) ?> <?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </footer>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif ($tab === 'incoming'): ?>
        <?php empty_state('inbox', $status ? 'No ' . strtolower(REQUEST_STATUSES[$status]) . ' requests' : 'No requests yet',
            'When another student asks to learn one of your skills, the request will appear here.',
            '<a class="btn btn--primary" href="skill_form.php?type=teach">Add a skill you can teach</a>'); ?>
    <?php else: ?>
        <?php empty_state('send', $status ? 'No ' . strtolower(REQUEST_STATUSES[$status]) . ' requests' : 'You have not sent any requests',
            'Find a student who teaches what you want to learn and send them a swap request.',
            '<a class="btn btn--primary" href="skills.php?type=teach">Browse skills</a>'); ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
