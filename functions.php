<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('
        SELECT id, username, email, is_active, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_guest(): void
{
    if (current_user() !== null) {
        header('Location: dashboard.php');
        exit;
    }
}

function require_login(): void
{
    if (current_user() === null) {
        header('Location: login.php');
        exit;
    }
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}

function password_hash_for_storage(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }

    if ($hash === false) {
        throw new RuntimeException('Unable to hash password.');
    }

    return $hash;
}

function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 12) {
        return 'Password must be at least 12 characters long.';
    }

    return null;
}

function find_user_by_username_or_email(string $value): ?array
{
    $stmt = db()->prepare('
        SELECT *
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1
    ');
    $stmt->execute([$value, $value]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $message;
}

function html_header(string $title): void
{
    echo '<!doctype html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . '</title>';
    echo '<style>
        body {
            font-family: system-ui, Arial, sans-serif;
            max-width: 720px;
            margin: 40px auto;
            padding: 0 16px;
            line-height: 1.5;
        }
        form {
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 12px;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: 600;
        }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            box-sizing: border-box;
        }
        button {
            margin-top: 16px;
            padding: 10px 14px;
        }
        .error {
            color: #a00;
            margin: 10px 0;
        }
        .success {
            color: #0a0;
            margin: 10px 0;
        }
        nav a {
            margin-right: 12px;
        }
    </style>';
    echo '</head>';
    echo '<body>';
    echo '<nav>';
    echo '<a href="index.php">Home</a>';

    if (current_user()) {
        echo '<a href="dashboard.php">Dashboard</a>';
        echo '<a href="change-password.php">Change Password</a>';
        echo '<a href="logout.php">Logout</a>';
    } else {
        echo '<a href="login.php">Login</a>';
        echo '<a href="register.php">Register</a>';
    }

    echo '</nav><hr>';
}

function html_footer(): void
{
    echo '</body></html>';
}

function send_password_reset_email(string $email, string $resetUrl): void
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSendmail();

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($email);

        $mail->Subject = APP_NAME.': Password Reset';
        $mail->Body =
            "A password reset was requested for your account.\n\n" .
            "Use this link to reset your password:\n" .
            $resetUrl . "\n\n" .
            "If you did not request this, you can ignore this email.";

        $sent = $mail->send();

        if ($sent) {
            error_log('Email sent successfully to: ' . $email);
        } else {
            error_log('Email send failed: ' . $mail->ErrorInfo);
        }

    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
    }
}