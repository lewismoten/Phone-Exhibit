<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$terms = current_terms();

if (!$terms) {
    http_response_code(500);
    exit('No active terms are configured.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $agree = $_POST['agree'] ?? '';

    if ($agree !== '1') {
        $error = 'You must agree to continue.';
    } else {
        $stmt = db()->prepare('
            UPDATE users
            SET
                agreed_terms_version = ?,
                agreed_to_terms_at = NOW(),
                last_terms_seen_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([
            $terms['version'],
            $user['id'],
        ]);

        flash_set('success', 'Agreement accepted.');

        $redirect = trim((string)($_POST['redirect'] ?? 'dashboard.php'));
        if ($redirect === '' || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
            $redirect = 'dashboard.php';
        }

        header('Location: ' . $redirect);
        exit;
    }
}

$redirect = trim((string)($_GET['redirect'] ?? 'dashboard.php'));
if ($redirect === '' || str_contains($redirect, '://') || str_starts_with($redirect, '//')) {
    $redirect = 'dashboard.php';
}

html_header('Accept Terms');
?>

<?php require __DIR__ . '/legal-summary.php'; ?>

<h1>Audio Contribution and Public Exhibition Agreement</h1>

<p><strong>Version:</strong> <?= e((string)$terms['version']) ?></p>

<div style="border:1px solid #ddd;padding:16px;border-radius:12px;white-space:pre-wrap;line-height:1.6;">
    <?= e((string)$terms['content']) ?>
</div>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" style="margin-top:16px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

    <label style="display:flex;gap:8px;align-items:flex-start;font-weight:normal;">
        <input type="checkbox" name="agree" value="1" required style="width:auto;margin-top:4px;">
        <span>
            I have read and agree to version <?= e((string)$terms['version']) ?> of the
            Audio Contribution and Public Exhibition Agreement.
        </span>
    </label>

    <button type="submit">Accept and Continue</button>
</form>

<?php html_footer(); ?>