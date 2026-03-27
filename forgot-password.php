<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_guest();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string)($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = (new DateTimeImmutable('+' . PASSWORD_RESET_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

            $clearOld = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
            $clearOld->execute([$user['id']]);

            $insert = db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
            $insert->execute([$user['id'], $tokenHash, $expiresAt]);

            $resetUrl = BASE_URL . '/reset-password.php?token=' . urlencode($token);
            send_password_reset_email($user['email'], $resetUrl);
        }

        $success = 'If that email exists in our system, a reset link has been sent.';
    }
}

html_header('Forgot Password');
?>
<h1>Forgot password</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" required>

    <button type="submit">Send reset link</button>
</form>
<?php html_footer(); ?>