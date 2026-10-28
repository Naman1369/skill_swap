</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <a class="brand" href="index.php">
                <img src="assets/logo.svg" alt="" width="28" height="28">
                <span>SkillSwap</span>
            </a>
            <p>Teach what you know. Learn what you don't. A peer-to-peer skill exchange for students.</p>
        </div>
        <nav class="site-footer__links" aria-label="Footer">
            <a href="skills.php">Browse skills</a>
            <?php if (is_logged_in()): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="skill_form.php">Add a skill</a>
            <?php else: ?>
                <a href="register.php">Create an account</a>
                <a href="login.php">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="container site-footer__bottom">
        <p>&copy; <?= date('Y') ?> SkillSwap · BCA Summer Internship Project, MIT School of Business &amp; Computer Applications, Pune</p>
    </div>
</footer>

<script src="js/script.js"></script>
</body>
</html>
