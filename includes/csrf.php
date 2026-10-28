<?php
/**
 * CSRF protection: every form that changes data includes a per-session token,
 * and every POST handler calls verify_csrf() before doing anything.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        abort(400, 'Your session has expired or the form was submitted from another site. Please go back, refresh the page and try again.');
    }
}
