<?php
/**
 * General helper functions and shared constants.
 */

const SKILL_TYPES = [
    'teach' => 'Can teach',
    'learn' => 'Wants to learn',
];

const SKILL_LEVELS = [
    'beginner'     => 'Beginner',
    'intermediate' => 'Intermediate',
    'advanced'     => 'Advanced',
];

const SKILL_MODES = [
    'online'  => 'Online',
    'offline' => 'In person',
    'both'    => 'Online or in person',
];

const YEARS_OF_STUDY = ['1st year', '2nd year', '3rd year', '4th year', '5th year', 'Postgraduate', 'Alumni'];

const REQUEST_STATUSES = [
    'pending'   => 'Pending',
    'accepted'  => 'Accepted',
    'completed' => 'Completed',
    'rejected'  => 'Declined',
    'cancelled' => 'Cancelled',
];

/* ---------- Output & navigation ---------- */

/** Escape a value for safe output in HTML. Use for ALL user-provided content. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Stop and render the error page with the given HTTP status. */
function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $errorCode = $code;
    $errorMessage = $message;
    require __DIR__ . '/../404.php';
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------- Request input ---------- */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post_str(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_str(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function post_int(string $key): int
{
    $value = $_POST[$key] ?? '';
    return (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
}

function get_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? '';
    return (is_string($value) && ctype_digit($value)) ? (int) $value : $default;
}

/** Current query string with some parameters replaced (empty values are dropped). */
function query_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return $params ? '?' . http_build_query($params) : '?';
}

/* ---------- Formatting ---------- */

function excerpt(?string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0];
}

function time_ago(string $datetime): string
{
    $seconds = time() - strtotime($datetime);
    if ($seconds < 60) {
        return 'just now';
    }
    $units = [86400 * 30 => null, 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $size => $unit) {
        if ($seconds >= $size) {
            if ($unit === null) {
                return date('j M Y', strtotime($datetime));
            }
            $n = intdiv($seconds, $size);
            return $n . ' ' . $unit . ($n === 1 ? '' : 's') . ' ago';
        }
    }
    return 'just now';
}

function avatar_url(?string $file): ?string
{
    if ($file && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $file) && is_file(AVATAR_DIR . $file)) {
        // Served by avatar.php so the upload folder can live outside the web root (e.g. a Docker volume).
        return 'avatar.php?f=' . $file;
    }
    return null;
}

/* ---------- Data helpers ---------- */

/** Common SELECT for skill listings with their category and owner. Append a WHERE clause. */
function skill_select_sql(): string
{
    return 'SELECT s.id, s.user_id, s.title, s.description, s.type, s.level, s.mode, s.created_at,
                   c.id AS category_id, c.name AS category_name, c.icon AS category_icon,
                   u.name AS owner_name, u.status AS owner_status, p.avatar AS owner_avatar, p.course AS owner_course
            FROM skills s
            JOIN categories c ON c.id = s.category_id
            JOIN users u ON u.id = s.user_id
            LEFT JOIN profiles p ON p.user_id = u.id';
}

function categories(): array
{
    static $categories = null;
    if ($categories === null) {
        $categories = db()->query('SELECT id, name, icon FROM categories ORDER BY id')->fetchAll();
    }
    return $categories;
}

function category_exists(int $id): bool
{
    foreach (categories() as $category) {
        if ((int) $category['id'] === $id) {
            return true;
        }
    }
    return false;
}

function pending_incoming_count(int $userId): int
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM exchange_requests WHERE receiver_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Contact details are private: they are shown only to the owner and to students
 * who have an accepted or completed exchange with them.
 */
function can_view_contact(?int $viewerId, int $ownerId): bool
{
    if ($viewerId === null) {
        return false;
    }
    if ($viewerId === $ownerId) {
        return true;
    }
    $stmt = db()->prepare(
        "SELECT 1 FROM exchange_requests
         WHERE status IN ('accepted', 'completed')
           AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
         LIMIT 1"
    );
    $stmt->execute([$viewerId, $ownerId, $ownerId, $viewerId]);
    return (bool) $stmt->fetchColumn();
}

/** @return array{page:int, pages:int, offset:int, total:int, per_page:int} */
function paginate(int $total, int $page, int $perPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return [
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
        'total'    => $total,
        'per_page' => $perPage,
    ];
}

/**
 * Profile checklist used on the dashboard.
 * @return array{percent:int, missing:string[]}
 */
function profile_completeness(array $user, int $skillCount): array
{
    $checks = [
        'Add your course'               => !empty($user['course']),
        'Add your year of study'        => !empty($user['year_of_study']),
        'Add your college'              => !empty($user['college']),
        'Write a short bio'             => !empty($user['bio']),
        'Upload a profile photo'        => avatar_url($user['avatar'] ?? null) !== null,
        'Add a phone number or link'    => !empty($user['phone']) || !empty($user['contact_link']),
        'List at least one skill'       => $skillCount > 0,
    ];
    $done = count(array_filter($checks));
    return [
        'percent' => (int) round($done / count($checks) * 100),
        'missing' => array_keys(array_filter($checks, fn ($ok) => !$ok)),
    ];
}
