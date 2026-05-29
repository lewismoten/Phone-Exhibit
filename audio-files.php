<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('My Audio Files');
?>

<h1>My audio files</h1>

<p><a href="upload-audio.php">Upload another file</a></p>

<form id="audio-search-form" style="margin-bottom:16px;">
    <label for="q">Search</label>
    <input id="q" name="q" placeholder="Search filename, title, phone number, or transcript">
    <p>
        <a id="audio-search-submit" class="button" href="#">Search</a>
    </p>
</form>

<div id="audio-results">Loading…</div>
<nav id="audio-pagination" style="margin-top:16px;"></nav>

<script src="/common.js"></script>
<script defer src="/audio-files.js"></script>

<?php html_footer(); ?>
