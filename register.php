<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_guest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $agree = $_POST['agree'];

    if ($username === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (empty($agree)) {
      $error = 'You must agree to the notice to create an account.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z0-9_\-.]{3,50}$/', $username)) {
        $error = 'Username must be 3-50 characters and use letters, numbers, dot, dash, or underscore.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($msg = validate_password_strength($password)) {
        $error = $msg;
    } elseif (find_user_by_username_or_email($username) || find_user_by_username_or_email($email)) {
        $error = 'That username or email is already in use.';
        
    } else {
        $terms = current_terms();

        $stmt = db()->prepare('
            INSERT INTO users (
                username,
                email,
                password_hash,
                agreed_terms_version,
                agreed_to_terms_at,
                last_terms_seen_at
            )
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ');

        $stmt->execute([
            $username,
            $email,
            password_hash_for_storage($password),
            $terms['version'],
        ]);

        login_user((int)db()->lastInsertId());
        header('Location: dashboard.php');
        exit;
    }
}

html_header('Create Account');
?>
<h1>Create account</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="username">Username</label>
    <input id="username" name="username" required maxlength="50" value="<?= e((string)($_POST['username'] ?? '')) ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" required maxlength="255" value="<?= e((string)($_POST['email'] ?? '')) ?>">

    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>

    <label for="confirm_password">Confirm password</label>
    <input id="confirm_password" name="confirm_password" type="password" required>

    <?php require __DIR__ . '/legal-summary.php'; ?>
    <?php require __DIR__ . '/legal-notice.php'; ?>

    <label>
    <input type="checkbox" name="agree" value="1" required>
    I agree to the Audio Contribution & Exhibit Notice
    </label>

    <button type="submit">Create account</button>
</form>
<?php html_footer(); ?>