<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
html_header('Dashboard');
?>
<h1>Dashboard</h1>
<p>Welcome, <strong><?= e($user['username']) ?></strong>.</p>
<ul>
  <li><a href="audio-files.php">My audio files</a></li>
  <li><a href="upload-audio.php">Upload audio</a></li>
  <li><a href="record-audio.php">Record audio</a></li>
  <li><a href="legal.php">Legal Notice</a></li>
  <li><a href="change-password.php">Change password</a></li>
  <li><a href="logout.php">Logout</a></li>
</ul>
<?= get_site_content('dashboard_callout') ?>

<?php html_footer(); ?>