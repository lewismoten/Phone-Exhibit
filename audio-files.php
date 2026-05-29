<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

html_header('My Audio Files');
?>

<h1>My audio files</h1>

<div class="audio-action-panels">
    <div class="card secondary audio-action-card">
        <?php html_border_pieces(); ?>
        <h1 class="center">Upload</h1>
        <hr>

        <div class="audio-action-card-body center">
            <div id="audio-upload-status"></div>
            <form id="audio-upload-form" class="audio-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input id="audio_upload_file" name="audio_file" type="file" accept="audio/*" required>
                <p><a id="audio-upload-submit" class="button" href="#">Upload audio file</a></p>
            </form>
        </div>
    </div>

    <div class="card secondary audio-search-card">
        <?php html_border_pieces(); ?>
        <h1 class="center">Search</h1>
        <hr>

        <form id="audio-search-form" class="audio-search-form">
            <div class="audio-search-form-row">
                <input id="q" name="q" placeholder="Search filename, title, phone number, or transcript">
                <a id="audio-search-submit" class="button" href="#">Search</a>
            </div>
        </form>
    </div>
</div>

<div id="audio-results">Loading…</div>
<nav id="audio-pagination" style="margin-top:16px;"></nav>

<script src="/common.js"></script>
<script defer src="/audio-files.js"></script>

<?php html_footer(); ?>
