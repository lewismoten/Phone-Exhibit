<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

html_header('Phone Directory');
?>

<div class="card secondary phone-directory-page">
    <?php html_border_pieces(); ?>
    <h1 class="center">
        <?php html_span('icon book'); ?>
        Phone Directory
    </h1>
    <hr>
    <div id="phone-directory-results" class="phone-directory-results">Loading directory…</div>
</div>

<script defer src="/phone-directory.js"></script>

<?php html_footer(); ?>
