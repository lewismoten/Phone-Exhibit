<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();

$error = '';
$success = '';
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif ($msg = validate_password_strength($newPassword)) {
        $error = $msg;
    } else {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash_for_storage($newPassword), $user['id']]);
        session_regenerate_id(true);
        $success = 'Password changed successfully.';
    }
}

html_header('Change Password');
?>
<h1>Change password</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="current_password">Current password</label>
    <input id="current_password" name="current_password" type="password" required>

    <label for="new_password">New password</label>
    <input id="new_password" name="new_password" type="password" required>

    <label for="confirm_password">Confirm new password</label>
    <input id="confirm_password" name="confirm_password" type="password" required>

    <button type="submit">Update password</button>
</form>
<?php html_footer(); ?>