<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

html_header(APP_NAME);
$user = current_user();
?>
<h1>Phone Exhibits</h1>
<p>Authentication system for contributor accounts.</p>

<?= get_site_content('homepage_callout') ?>

<?php if ($user): ?>
    <p>You are signed in as <strong><?= e($user['username']) ?></strong>.</p>
    <p><a href="dashboard.php">Go to dashboard</a></p>
<?php else: ?>
    <p><a href="login.php">Login</a> or <a href="register.php">create an account</a>.</p>
<?php endif; ?>
<?php html_footer(); ?>