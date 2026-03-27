<?php
declare(strict_types=1);

if (!function_exists('current_terms')) {
    require_once __DIR__ . '/functions.php';
}

$terms = current_terms();
$user = current_user();

?>

<div class="legal-notice" style="border:1px solid #ccc;padding:16px;border-radius:12px;margin:16px 0;">
    <h2>Audio Contribution and Public Exhibition Agreement</h2>

    <?php if (!$terms): ?>
        <div class="error">No active agreement is currently configured.</div>
    <?php else: ?>
        <p>
            <strong>Version:</strong> <?= e((string)$terms['version']) ?><br>
            <strong>Effective:</strong>
            <?php if (!empty($terms['created_at'])): ?>
                <time
                    class="local-datetime"
                    datetime="<?= e(gmdate('c', strtotime((string)$terms['created_at']))) ?>"
                    data-utc="<?= e(gmdate('c', strtotime((string)$terms['created_at']))) ?>"
                >
                    <?= e(gmdate('M j, Y g:i A', strtotime((string)$terms['created_at']))) ?> UTC
                </time>
                <span class="local-timezone-label" style="color:#666;"></span>
            <?php else: ?>
                Current
            <?php endif; ?>
        </p>
        <?php if (!empty($user['agreed_to_terms_at'])): ?>
        <p>
            <strong>Your accepted version:</strong> <?= e((string)($user['agreed_terms_version'] ?? 'None')) ?><br />
            <strong>You accepted:</strong>
            <time class="local-datetime"
                  data-utc="<?= e(gmdate('c', strtotime((string)$user['agreed_to_terms_at']))) ?>">
                <?= e(gmdate('M j, Y g:i A', strtotime((string)$user['agreed_to_terms_at']))) ?> UTC
            </time>
            <span class="local-timezone-label" style="color:#666;"></span>
        </p>
        <?php endif; ?>
    
        <div style="white-space:pre-wrap;line-height:1.6;">
            <?= e((string)$terms['content']) ?>
        </div>
    <?php endif; ?>
</div>