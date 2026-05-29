<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

require_guest();

html_header('Login');
?>
<noscript>
    <div class="card secondary">
        <?php html_border_pieces(); ?>
        <h1 class="center">Login</h1>
        <p>Please enable JavaScript to use the login dialog.</p>
    </div>
</noscript>

<script>
onReady(() => {
    if (typeof open_login_modal === 'function') {
        open_login_modal();
    }
});
</script>
<?php html_footer(); ?>
