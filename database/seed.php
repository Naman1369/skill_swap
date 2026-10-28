<?php
/**
 * Loads demo students, skills and exchange requests.
 * Passwords are hashed with password_hash(), which is why this is a PHP script and not plain SQL.
 *
 * Usage (from the project folder, after importing database/skillswap.sql):
 *   C:\xampp\php\php.exe database\seed.php           (only runs on an empty database)
 *   C:\xampp\php\php.exe database\seed.php --fresh   (deletes ALL users, skills and requests first)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

const DEMO_PASSWORD = 'Password@123';

if (APP_ENV === 'production' && !in_array('--allow-production', $argv, true)) {
    fwrite(STDERR, "Refusing to load demo data (known passwords) while APP_ENV=production. Pass --allow-production if you really mean it.\n");
    exit(1);
}

$pdo = db();
$fresh = in_array('--fresh', $argv, true);

$existing = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($existing > 0 && !$fresh) {
    fwrite(STDERR, "The database already has {$existing} user(s). Run with --fresh to delete everything and reseed.\n");
    exit(1);
}

$categoryIds = [];
foreach ($pdo->query('SELECT id, name FROM categories') as $row) {
    $categoryIds[$row['name']] = (int) $row['id'];
}
if (!$categoryIds) {
    fwrite(STDERR, "No categories found. Import database/skillswap.sql first.\n");
    exit(1);
}

// key => [name, email, course, year, bio, phone, link, days since joining]
$students = [
    'aarav'  => ['Aarav Sharma', 'aarav@skillswap.test', 'BCA', '2nd year',
        'I love building websites and automating boring tasks with Python. Looking to get better at design and speaking in front of people.',
        '+91 98220 11001', 'https://github.com/', 40],
    'priya'  => ['Priya Deshmukh', 'priya@skillswap.test', 'BBA', '3rd year',
        'Designer at heart: posters, social media creatives and pitch decks. Want to learn some coding and data skills for my marketing projects.',
        '+91 98220 11002', 'https://www.linkedin.com/', 38],
    'rohan'  => ['Rohan Kulkarni', 'rohan@skillswap.test', 'B.Tech (Computer Science)', '3rd year',
        'Competitive programmer. Happy to help with Java, DSA and Git. Trying to learn guitar without annoying my roommates.',
        '+91 98220 11003', null, 35],
    'sneha'  => ['Sneha Patil', 'sneha@skillswap.test', 'BCA (Data Science)', '3rd year',
        'Data nerd. Excel, SQL and Power BI are my thing. Currently learning JavaScript and some French.',
        null, 'https://www.linkedin.com/', 30],
    'kabir'  => ['Kabir Mehta', 'kabir@skillswap.test', 'BBA (Marketing)', '2nd year',
        'I edit videos for our college fest and write content for a few student clubs. Want to learn how websites work.',
        '+91 98220 11005', null, 26],
    'ananya' => ['Ananya Iyer', 'ananya@skillswap.test', 'BA (English)', '2nd year',
        'Book lover and debate club member. I can help with spoken English, grammar and creative writing.',
        '+91 98220 11006', null, 21],
    'vikram' => ['Vikram Joshi', 'vikram@skillswap.test', 'B.Com', '1st year',
        'Guitarist and part-time accounts helper at my family business. Want to get good at Excel dashboards.',
        null, null, 14],
    'isha'   => ['Isha Gupta', 'isha@skillswap.test', 'BCA', '1st year',
        'Maths enthusiast who just discovered JavaScript. Looking for a DSA mentor and someone to explain machine learning simply.',
        '+91 98220 11008', null, 9],
];

// key => [owner, category, title, type, level, mode, description, days ago]
$skills = [
    'aarav_python'  => ['aarav', 'Programming', 'Python Programming for Beginners', 'teach', 'intermediate', 'both',
        'Variables, loops, functions, files and small automation scripts. We will build a mini project together.', 38],
    'aarav_web'     => ['aarav', 'Web Development', 'Responsive Websites with HTML & CSS', 'teach', 'advanced', 'online',
        'Semantic HTML, Flexbox, Grid and media queries. Bring a page you want to build and we will make it responsive.', 37],
    'aarav_canva'   => ['aarav', 'Design', 'Graphic Design with Canva', 'learn', 'beginner', 'both', 'Want to make better posters for club events.', 36],
    'aarav_speak'   => ['aarav', 'Communication', 'Public Speaking', 'learn', 'beginner', 'offline', 'I get nervous during presentations and want to improve.', 36],

    'priya_canva'   => ['priya', 'Design', 'Canva & Figma Design', 'teach', 'advanced', 'both',
        'Layouts, colour, typography and building social media templates. Figma basics for UI mock-ups too.', 35],
    'priya_speak'   => ['priya', 'Communication', 'Public Speaking & Presentations', 'teach', 'intermediate', 'offline',
        'Structuring a talk, handling stage fright, and designing slides that support your message.', 34],
    'priya_python'  => ['priya', 'Programming', 'Python Basics', 'learn', 'beginner', 'online', 'For automating marketing reports.', 34],
    'priya_excel'   => ['priya', 'Data & AI', 'Excel for Data Analysis', 'learn', 'intermediate', 'both', null, 33],

    'rohan_java'    => ['rohan', 'Programming', 'Java & Data Structures', 'teach', 'advanced', 'online',
        'Arrays, linked lists, stacks, trees and graphs in Java, with practice problems for placements.', 33],
    'rohan_git'     => ['rohan', 'Web Development', 'Git & GitHub Essentials', 'teach', 'intermediate', 'online',
        'Commits, branches, pull requests and fixing merge conflicts without panic.', 32],
    'rohan_guitar'  => ['rohan', 'Music & Arts', 'Guitar Basics', 'learn', 'beginner', 'offline', null, 31],
    'rohan_video'   => ['rohan', 'Design', 'Video Editing', 'learn', 'beginner', 'online', 'For my YouTube coding tutorials.', 31],

    'sneha_excel'   => ['sneha', 'Data & AI', 'Excel & Power BI Dashboards', 'teach', 'advanced', 'both',
        'Pivot tables, lookups, charts and interactive Power BI dashboards from real datasets.', 29],
    'sneha_sql'     => ['sneha', 'Data & AI', 'SQL Queries from Scratch', 'teach', 'intermediate', 'online',
        'SELECT, JOINs, GROUP BY and subqueries using MySQL. Great for DBMS exams too.', 28],
    'sneha_js'      => ['sneha', 'Web Development', 'JavaScript Fundamentals', 'learn', 'beginner', 'online', null, 28],
    'sneha_french'  => ['sneha', 'Languages', 'Basic French', 'learn', 'beginner', 'both', null, 27],

    'kabir_video'   => ['kabir', 'Design', 'Video Editing with Premiere Pro', 'teach', 'intermediate', 'both',
        'Cuts, transitions, colour correction and exporting for Instagram and YouTube.', 25],
    'kabir_writing' => ['kabir', 'Communication', 'Content Writing for Social Media', 'teach', 'advanced', 'online',
        'Hooks, captions, blog structure and writing for your audience.', 24],
    'kabir_photo'   => ['kabir', 'Music & Arts', 'Mobile Photography', 'teach', 'intermediate', 'offline',
        'Composition, lighting and editing photos on your phone.', 24],
    'kabir_web'     => ['kabir', 'Web Development', 'Building a Personal Website', 'learn', 'beginner', 'online', null, 23],
    'kabir_sql'     => ['kabir', 'Data & AI', 'SQL Basics', 'learn', 'beginner', 'online', null, 23],

    'ananya_english' => ['ananya', 'Languages', 'Spoken English & Grammar', 'teach', 'advanced', 'both',
        'Conversation practice, common grammar mistakes and interview English.', 20],
    'ananya_writing' => ['ananya', 'Communication', 'Creative Writing', 'teach', 'intermediate', 'online',
        'Short stories, poetry and finding your own voice.', 20],
    'ananya_french'  => ['ananya', 'Languages', 'French for Beginners', 'teach', 'beginner', 'online',
        'Greetings, everyday phrases and pronunciation (A1 level).', 19],
    'ananya_python'  => ['ananya', 'Programming', 'Python for Non-programmers', 'learn', 'beginner', 'online', null, 19],
    'ananya_canva'   => ['ananya', 'Design', 'Canva Posters', 'learn', 'beginner', 'both', null, 18],

    'vikram_guitar'  => ['vikram', 'Music & Arts', 'Acoustic Guitar for Beginners', 'teach', 'intermediate', 'offline',
        'Chords, strumming patterns and your first three songs.', 13],
    'vikram_tally'   => ['vikram', 'Academics', 'Accounting Basics & Tally', 'teach', 'intermediate', 'both',
        'Journal entries, ledgers and GST basics in Tally Prime.', 12],
    'vikram_excel'   => ['vikram', 'Data & AI', 'Excel Dashboards', 'learn', 'intermediate', 'both', null, 12],
    'vikram_speak'   => ['vikram', 'Communication', 'Confidence in Public Speaking', 'learn', 'beginner', 'offline', null, 11],

    'isha_maths'     => ['isha', 'Academics', 'Mathematics for BCA', 'teach', 'advanced', 'both',
        'Discrete maths, statistics and numerical methods explained with lots of examples.', 8],
    'isha_js'        => ['isha', 'Web Development', 'JavaScript Basics', 'teach', 'intermediate', 'online',
        'DOM manipulation, events and small interactive projects.', 8],
    'isha_java'      => ['isha', 'Programming', 'Java & DSA', 'learn', 'intermediate', 'online', null, 7],
    'isha_ml'        => ['isha', 'Data & AI', 'Machine Learning Basics', 'learn', 'beginner', 'online', null, 7],
];

// [sender, requested skill, offered skill|null, status, message, days ago]
$requests = [
    ['priya',  'aarav_python',   'priya_canva',    'accepted',
        'Hi Aarav! I would love to learn Python basics to automate my marketing reports. I can help you with Canva in return.', 6],
    ['aarav',  'priya_speak',    'aarav_web',      'pending',
        'Hey Priya, I have a seminar coming up and would really like some public speaking tips. Happy to help you build a website!', 2],
    ['kabir',  'aarav_web',      'kabir_video',    'pending',
        'Hi Aarav, I want to build a portfolio site for my video work. I can teach you video editing in return.', 1],
    ['sneha',  'isha_js',        'sneha_sql',      'completed',
        'Hi Isha! Could you help me with JavaScript basics? I can teach you SQL for your DBMS exams.', 7],
    ['vikram', 'sneha_excel',    'vikram_guitar',  'pending',
        'Hi Sneha, I need to build Excel dashboards for our family shop. I can teach you guitar if you are interested!', 3],
    ['isha',   'rohan_java',     'isha_maths',     'accepted',
        'Hi Rohan, I am preparing for coding rounds and need help with DSA in Java. I can help with maths subjects.', 5],
    ['ananya', 'priya_canva',    'ananya_english', 'pending',
        'Hi Priya! I would like to learn how to make posters for our literary club. I can help with spoken English.', 1],
    ['rohan',  'vikram_guitar',  'rohan_git',      'completed',
        'Hey Vikram, I have wanted to learn guitar for ages. I can teach you Git, which is useful for any project.', 10],
    ['kabir',  'sneha_sql',      'kabir_writing',  'rejected',
        'Hi Sneha, could you teach me SQL basics? I can help you with content writing.', 4],
];

try {
    $pdo->beginTransaction();

    if ($fresh) {
        $pdo->exec('DELETE FROM exchange_requests');
        $pdo->exec('DELETE FROM skills');
        $pdo->exec('DELETE FROM profiles');
        $pdo->exec('DELETE FROM users');
    }

    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW() - INTERVAL ? DAY)');
    $insertProfile = $pdo->prepare(
        "INSERT INTO profiles (user_id, course, year_of_study, college, bio, phone, contact_link)
         VALUES (?, ?, ?, 'MIT School of Business & Computer Applications, Pune', ?, ?, ?)"
    );
    $userIds = [];
    foreach ($students as $key => [$name, $email, $course, $year, $bio, $phone, $link, $days]) {
        $insertUser->execute([$name, $email, $hash, $days]);
        $userIds[$key] = (int) $pdo->lastInsertId();
        $insertProfile->execute([$userIds[$key], $course, $year, $bio, $phone, $link]);
    }

    $insertSkill = $pdo->prepare(
        'INSERT INTO skills (user_id, category_id, title, description, type, level, mode, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)'
    );
    $skillIds = [];
    foreach ($skills as $key => [$owner, $category, $title, $type, $level, $mode, $description, $days]) {
        $insertSkill->execute([$userIds[$owner], $categoryIds[$category], $title, $description, $type, $level, $mode, $days]);
        $skillIds[$key] = (int) $pdo->lastInsertId();
    }

    $insertRequest = $pdo->prepare(
        'INSERT INTO exchange_requests (sender_id, receiver_id, skill_id, offered_skill_id, message, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY, NOW() - INTERVAL ? DAY)'
    );
    foreach ($requests as [$sender, $skillKey, $offeredKey, $status, $message, $days]) {
        $receiver = $skills[$skillKey][0];
        $insertRequest->execute([
            $userIds[$sender],
            $userIds[$receiver],
            $skillIds[$skillKey],
            $offeredKey ? $skillIds[$offeredKey] : null,
            $message,
            $status,
            $days,
            max(0, $days - 1),
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "Seeded %d students, %d skills and %d exchange requests.\nLog in with any demo email (e.g. aarav@skillswap.test) and password %s\n",
    count($students), count($skills), count($requests), DEMO_PASSWORD
);
