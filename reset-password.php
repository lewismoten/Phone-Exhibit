<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_guest();

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

function password_reset_record(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email
         FROM password_resets pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ($row['used_at'] !== null) {
        return null;
    }

    if (new DateTimeImmutable($row['expires_at']) < new DateTimeImmutable()) {
        return null;
    }

    return $row;
}

$record = password_reset_record($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $record = password_reset_record($token);

    if (!$record) {
        $error = 'This reset link is invalid or expired.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($msg = validate_password_strength($password)) {
        $error = $msg;
    } else {
        $updateUser = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateUser->execute([password_hash_for_storage($password), $record['user_id']]);

        $useToken = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $useToken->execute([$record['id']]);

        flash_set('success', 'Your password has been reset. You can log in now.');
        header('Location: login.php');
        exit;
    }
}

html_header('Reset Password');
?>
<h1>Reset password</h1>
<?php if (!$record && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
    <div class="error">This reset link is invalid or expired.</div>
<?php else: ?>
    <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label for="password">New password</label>
        <input id="password" name="password" type="password" required>

        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" required>

        <button type="submit">Reset password</button>
    </form>
<?php endif; ?>
<?php html_footer(); ?>