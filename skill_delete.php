<?php
require __DIR__ . '/includes/init.php';
require_login();

if (!is_post()) {
    redirect('profile.php#skills');
}
verify_csrf();

$me = current_user();
$skillId = post_int('id');

// The user_id condition makes sure students can only delete their own listings.
$stmt = db()->prepare('SELECT title FROM skills WHERE id = ? AND user_id = ?');
$stmt->execute([$skillId, $me['id']]);
$title = $stmt->fetchColumn();

if ($title === false) {
    abort(403, 'You can only delete your own skill listings.');
}

db()->prepare('DELETE FROM skills WHERE id = ? AND user_id = ?')->execute([$skillId, $me['id']]);
flash('success', '“' . $title . '” has been removed from your skills.');
redirect('profile.php#skills');
