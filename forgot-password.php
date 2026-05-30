<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

require_guest();

html_header('Forgot Password');
?>
<noscript>
    <div class="card secondary">
        <?php html_border_pieces(); ?>
        <h1 class="center">Forgot Password</h1>
        <p>Please enable JavaScript to use the password reset dialog.</p>
    </div>
</noscript>

<script>
onReady(() => {
    if (typeof open_login_modal === 'function' && typeof switch_login_modal_mode === 'function') {
        open_login_modal();
        switch_login_modal_mode('forgot');
    }
});
</script>
<?php html_footer(); ?>
