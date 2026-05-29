<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('Upload Audio');
?>
<h1>Upload audio</h1>
<div id="upload-audio-status"></div>
<form id="upload-audio-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="audio_file">Audio file</label>
    <input id="audio_file" name="audio_file" type="file" accept="audio/*" required>

    <button type="submit">Upload</button>
</form>
<p><a href="audio-files.php">View uploaded audio</a></p>
<script defer src="/upload-audio.js"></script>
<?php html_footer(); ?>
