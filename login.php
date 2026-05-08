<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_guest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $error = 'Username/email and password are required.';
    } else {
        $user = find_user_by_username_or_email($identity);

        if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid login credentials.';
        } else {
            if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash_for_storage($password), $user['id']]);
            }

            $terms = current_terms();
            login_user((int)$user['id'], (string)$user['role']);
            if ($user['agreed_terms_version'] !== $terms['version']) {
              header('Location: accept-terms.php');
            } else {
              header('Location: dashboard.php');
            }
            exit;
        }
    }
}

html_header('Login');
?>
<h1>Login</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<?php if ($msg = flash_get('success')): ?><div class="success"><?= e($msg) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="identity">Username or Email</label>
    <input id="identity" name="identity" required value="<?= e((string)($_POST['identity'] ?? '')) ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>

    <button type="submit">Login</button>
</form>
<p><a href="forgot-password.php">Forgot password?</a></p>
<?php html_footer(); ?>