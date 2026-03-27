<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
html_header('Dashboard');
?>
<h1>Dashboard</h1>
<p>Welcome, <strong><?= e($user['username']) ?></strong>.</p>
<p>This is where audio submission and management can go next.</p>
  <ul>
    <li><a href="upload-audio.php">Upload audio</a></li>
    <li><a href="audio-files.php">My audio files</a></li>
    <li><a href="change-password.php">Change password</a></li>
    <li><a href="logout.php">Logout</a></li>
</ul>
<?php html_footer(); ?>