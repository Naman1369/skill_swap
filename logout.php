<?php
require __DIR__ . '/includes/init.php';

// Logging out changes state, so it only happens through a POST form with a CSRF token.
if (!is_post()) {
    redirect('index.php');
}
verify_csrf();

logout_user();
flash('success', 'You have been logged out. See you soon!');
redirect('index.php');
