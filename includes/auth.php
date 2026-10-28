<?php
/**
 * Authentication and role-based access control (RBAC).
 *
 * Roles:
 *   student: the default. Can manage their own profile, skills and exchange requests only.
 *   admin:   everything a student can do, plus the admin area (user and listing moderation).
 * Roles are stored in users.role and checked on the server for every request; the user interface
 * only hides links, it never grants access.
 */

const ROLE_STUDENT = 'student';
const ROLE_ADMIN = 'admin';

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * The logged-in user joined with their profile, or null for guests.
 * Cached for the rest of the request. Suspended or deleted accounts are logged out here,
 * so a suspension takes effect on the very next request.
 */
function current_user(bool $refresh = false): ?array
{
    static $user = false;

    if ($refresh) {
        $user = false;
    }

    if ($user === false) {
        $user = null;
        $id = current_user_id();
        if ($id !== null) {
            $stmt = db()->prepare(
                'SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
                        p.course, p.year_of_study, p.college, p.bio, p.phone, p.contact_link, p.avatar
                 FROM users u
                 LEFT JOIN profiles p ON p.user_id = u.id
                 WHERE u.id = ?'
            );
            $stmt->execute([$id]);
            $found = $stmt->fetch() ?: null;

            if ($found === null) {
                unset($_SESSION['user_id']);
            } elseif ($found['status'] !== 'active') {
                unset($_SESSION['user_id']);
                session_regenerate_id(true);
                flash('error', 'Your account has been suspended. Please contact an administrator.');
            } else {
                $user = $found;
            }
        }
    }

    return $user;
}

/* ---------- Roles ---------- */

function user_role(): ?string
{
    $user = current_user();
    return $user ? $user['role'] : null;
}

function has_role(string $role): bool
{
    return user_role() === $role;
}

function is_admin(): bool
{
    return has_role(ROLE_ADMIN);
}

/**
 * Gate for pages that need a specific role. Guests are sent to the login page; logged-in users
 * without the role get a 403.
 */
function require_role(string $role): void
{
    require_login();
    if (!has_role($role)) {
        abort(403, 'You do not have permission to open this page.');
    }
}

function require_admin(): void
{
    require_role(ROLE_ADMIN);
}

/** Record an administrator action in the audit log. */
function audit_log(string $action, string $targetType, ?int $targetId, string $detail = ''): void
{
    $admin = current_user();
    db()->prepare(
        'INSERT INTO admin_audit (admin_id, admin_name, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $admin ? $admin['id'] : null,
        $admin ? $admin['name'] : 'unknown',
        $action,
        $targetType,
        $targetId,
        mb_substr($detail, 0, 255) ?: null,
    ]);
}

/* ---------- Login state ---------- */

/** Send guests to the login page, remembering where they were going. */
function require_login(): void
{
    if (is_logged_in()) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
    }
    flash('info', 'Please log in to continue.');
    redirect('login.php');
}

/** Logged-in users have no reason to see the login or register pages. */
function require_guest(): void
{
    if (is_logged_in()) {
        redirect('dashboard.php');
    }
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['csrf_token']);
    current_user(true);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Where to go after a successful login. Only same-site paths are allowed. */
function take_intended_url(string $default = 'dashboard.php'): string
{
    $url = $_SESSION['intended_url'] ?? '';
    unset($_SESSION['intended_url']);

    $isLocalPath = is_string($url)
        && str_starts_with($url, '/')
        && !str_starts_with($url, '//')
        && !str_contains($url, '\\');

    return $isLocalPath ? $url : $default;
}
