<?php
require __DIR__ . '/includes/init.php';

$pdo = db();
$stats = [
    'students'  => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
    'skills'    => (int) $pdo->query("SELECT COUNT(*) FROM skills s JOIN users u ON u.id = s.user_id WHERE u.status = 'active'")->fetchColumn(),
    'exchanges' => (int) $pdo->query("SELECT COUNT(*) FROM exchange_requests WHERE status IN ('accepted', 'completed')")->fetchColumn(),
];

$categoryCounts = $pdo->query(
    "SELECT c.id, c.name, c.icon, COUNT(s.id) AS total
     FROM categories c
     LEFT JOIN (SELECT sk.id, sk.category_id FROM skills sk JOIN users us ON us.id = sk.user_id AND us.status = 'active') s
            ON s.category_id = c.id
     GROUP BY c.id, c.name, c.icon
     ORDER BY c.id"
)->fetchAll();

$latest = $pdo->query(skill_select_sql() . " WHERE s.type = 'teach' AND u.status = 'active' ORDER BY s.created_at DESC, s.id DESC LIMIT 6")->fetchAll();

$loggedIn = is_logged_in();
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__copy">
            <span class="eyebrow"><?= icon('zap', 14) ?> Peer-to-peer learning for students</span>
            <h1>Teach what you know.<br><span class="gradient-text">Learn what you don't.</span></h1>
            <p class="hero__lead">
                SkillSwap connects students who want to share their skills. List what you can teach,
                say what you want to learn, and swap knowledge with peers on your campus.
            </p>
            <div class="hero__cta">
                <?php if ($loggedIn): ?>
                    <a class="btn btn--primary btn--lg" href="dashboard.php">Go to dashboard <?= icon('arrow-right', 18) ?></a>
                    <a class="btn btn--secondary btn--lg" href="skills.php">Browse skills</a>
                <?php else: ?>
                    <a class="btn btn--primary btn--lg" href="register.php">Get started free <?= icon('arrow-right', 18) ?></a>
                    <a class="btn btn--secondary btn--lg" href="skills.php">Browse skills</a>
                <?php endif; ?>
            </div>
            <ul class="hero__stats">
                <li><strong><?= $stats['students'] ?></strong><span>students</span></li>
                <li><strong><?= $stats['skills'] ?></strong><span>skills listed</span></li>
                <li><strong><?= $stats['exchanges'] ?></strong><span>swaps arranged</span></li>
            </ul>
        </div>

        <div class="hero__visual" aria-hidden="true">
            <div class="swap-demo">
                <div class="swap-demo__card swap-demo__card--a">
                    <span class="avatar avatar--md avatar--tone1">AS</span>
                    <div>
                        <small>Aarav can teach</small>
                        <strong>💻 Python programming</strong>
                    </div>
                </div>
                <div class="swap-demo__icon"><?= icon('swap', 26) ?></div>
                <div class="swap-demo__card swap-demo__card--b">
                    <span class="avatar avatar--md avatar--tone4">PD</span>
                    <div>
                        <small>Priya can teach</small>
                        <strong>🎨 Canva &amp; Figma design</strong>
                    </div>
                </div>
                <div class="swap-demo__match"><?= icon('check-circle', 16) ?> Mutual match: both learn something new</div>
            </div>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="container">
        <div class="section__intro">
            <h2>How SkillSwap works</h2>
            <p class="muted">Three simple steps from "I wish I knew that" to learning it from a classmate.</p>
        </div>
        <ol class="steps">
            <li class="step">
                <span class="step__num">1</span>
                <span class="step__icon"><?= icon('user', 22) ?></span>
                <h3>Create your profile</h3>
                <p>Sign up and tell others about your course, interests and how to reach you.</p>
            </li>
            <li class="step">
                <span class="step__num">2</span>
                <span class="step__icon"><?= icon('grid', 22) ?></span>
                <h3>List your skills</h3>
                <p>Add skills you can teach and skills you want to learn, with level and meeting mode.</p>
            </li>
            <li class="step">
                <span class="step__num">3</span>
                <span class="step__icon"><?= icon('swap', 22) ?></span>
                <h3>Request a swap</h3>
                <p>Find a peer, send a request and offer a skill in return. Once accepted, start learning together.</p>
            </li>
        </ol>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section__intro">
            <h2>Explore by category</h2>
            <p class="muted">From code to guitar chords, there's something for everyone.</p>
        </div>
        <div class="category-grid">
            <?php foreach ($categoryCounts as $category): ?>
                <a class="category-tile" href="skills.php?category=<?= (int) $category['id'] ?>">
                    <span class="category-tile__icon" aria-hidden="true"><?= e($category['icon']) ?></span>
                    <span class="category-tile__name"><?= e($category['name']) ?></span>
                    <span class="category-tile__count"><?= (int) $category['total'] ?> skill<?= (int) $category['total'] === 1 ? '' : 's' ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--alt">
    <div class="container">
        <div class="section__head">
            <div>
                <h2>Recently shared skills</h2>
                <p class="muted">Students ready to teach right now.</p>
            </div>
            <a class="link-arrow" href="skills.php?type=teach">See all skills <?= icon('arrow-right', 14) ?></a>
        </div>
        <?php if ($latest): ?>
            <div class="card-grid">
                <?php foreach ($latest as $skill) { skill_card($skill); } ?>
            </div>
        <?php else: ?>
            <?php empty_state('award', 'No skills listed yet', 'Be the first student to share a skill on SkillSwap.',
                '<a class="btn btn--primary" href="' . ($loggedIn ? 'skill_form.php' : 'register.php') . '">Share a skill</a>'); ?>
        <?php endif; ?>
    </div>
</section>

<?php if (!$loggedIn): ?>
<section class="section">
    <div class="container">
        <div class="cta-band">
            <div>
                <h2>Everyone has something to teach.</h2>
                <p>Join SkillSwap and turn what you already know into what you'll learn next.</p>
            </div>
            <a class="btn btn--light btn--lg" href="register.php">Create your free account</a>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
