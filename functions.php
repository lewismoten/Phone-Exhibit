<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/lib/getid3/getid3.php';

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
        SELECT 
          id,
          username,
          email,
          is_active,
          created_at,
          agreed_terms_version,
          agreed_to_terms_at,
          last_terms_seen_at
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
    echo '<script type="text/javascript" src="common.js"></script>';
    echo '<link rel="stylesheet" type="text/css" href="style.css" />';
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
    echo '<a href="legal.php">Legal Notice</a>';

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
function ensure_directory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
}

function audio_upload_dir_for_user(int $userId): string
{
    return rtrim(UPLOAD_BASE_DIR, '/') . DIRECTORY_SEPARATOR . $userId;
}

function human_file_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = (float)$bytes;
    $i = 0;

    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }

    return number_format($size, $size < 10 && $i > 0 ? 1 : 0) . ' ' . $units[$i];
}

function format_duration(?float $seconds): string
{
    if ($seconds === null) {
        return 'Unknown';
    }

    $total = (int)round($seconds);
    $hours = intdiv($total, 3600);
    $minutes = intdiv($total % 3600, 60);
    $secs = $total % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }

    return sprintf('%d:%02d', $minutes, $secs);
}

function channel_mode_label(?int $channels): string
{
    if ($channels === null) {
        return 'Unknown';
    }

    return match ($channels) {
        1 => 'Mono',
        2 => 'Stereo',
        default => $channels . ' channels',
    };
}

function safe_upload_filename(string $originalName): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $random = bin2hex(random_bytes(16));
    return $random . ($ext !== '' ? '.' . $ext : '');
}

function allowed_audio_mime_types(): array
{
    return [
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/flac' => 'flac',
        'audio/x-flac' => 'flac',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/ogg' => 'ogg',
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'application/ogg' => 'ogg',
    ];
}

function detect_uploaded_mime_type(string $tmpFile): string
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpFile);
    return is_string($mime) ? $mime : 'application/octet-stream';
}

function audio_public_url(array $row): string
{
    return rtrim(UPLOAD_BASE_URL, '/') . '/' . ltrim($row['user_id'] . '/' . $row['stored_filename'], '/');
}

function get_audio_file_for_user(int $id, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM audio_files WHERE id = ? AND user_id = ? AND is_deleted = 0 LIMIT 1');
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function extract_audio_metadata(string $fullPath, string $mimeType, string $extension): array
{
    $metadata = [
        'duration_seconds' => null,
        'audio_format' => strtoupper($extension),
        'audio_type' => $mimeType,
        'channels' => null,
        'channel_mode' => null,
        'sample_rate_hz' => null,
    ];

    if (class_exists('getID3')) {
        $analyzer = new getID3();
        $info = $analyzer->analyze($fullPath);

        $metadata['duration_seconds'] = isset($info['playtime_seconds']) ? (float)$info['playtime_seconds'] : null;
        $metadata['audio_format'] = (string)($info['fileformat'] ?? ($extension ?: 'unknown'));
        $metadata['audio_type'] = (string)($info['mime_type'] ?? $mimeType);
        $metadata['channels'] = isset($info['audio']['channels']) ? (int)$info['audio']['channels'] : null;
        $metadata['sample_rate_hz'] = isset($info['audio']['sample_rate']) ? (int)$info['audio']['sample_rate'] : null;
        $metadata['channel_mode'] = channel_mode_label($metadata['channels']);
    }

    return $metadata;
}

function pagination_offset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}

function ffmpeg_exists(): bool
{
    return is_file(FFMPEG_BIN) && is_executable(FFMPEG_BIN);
}

function converted_wav_filename(string $storedFilename): string
{
    $base = pathinfo($storedFilename, PATHINFO_FILENAME);
    return $base . '.phone.wav';
}

function convert_audio_for_phone(string $inputPath, string $outputPath): array
{
    if (!ffmpeg_exists()) {
        throw new RuntimeException('FFmpeg is not installed or not executable.');
    }

    $filter = 'highpass=f=300,lowpass=f=3000';

    $cmd = sprintf(
        '%s -y -i %s -ac 1 -ar 8000 -c:a pcm_s16le -af %s %s 2>&1',
        escapeshellarg(FFMPEG_BIN),
        escapeshellarg($inputPath),
        escapeshellarg($filter),
        escapeshellarg($outputPath)
    );

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    return [
        'success' => $exitCode === 0 && is_file($outputPath),
        'exit_code' => $exitCode,
        'log' => implode("\n", $output),
    ];
}

function audio_playback_url(array $row): string
{
    if (!empty($row['converted_relative_path'])) {
        return rtrim(UPLOAD_BASE_URL, '/') . '/' . ltrim($row['converted_relative_path'], '/');
    }

    return rtrim(UPLOAD_BASE_URL, '/') . '/' . ltrim($row['relative_path'], '/');
}
function current_terms(): ?array
{
    $stmt = db()->query("
        SELECT id, version, content, created_at
        FROM terms_versions
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");

    $row = $stmt->fetch();
    return $row ?: null;
}
function require_current_terms_acceptance(): void
{
    $user = current_user();
    $terms = current_terms();

    if (!$user || !$terms) {
        return;
    }

    $userVersion = trim((string)($user['agreed_terms_version'] ?? ''));
    $currentVersion = trim((string)($terms['version'] ?? ''));

    if ($userVersion !== $currentVersion) {
        $current = basename((string)($_SERVER['PHP_SELF'] ?? 'dashboard.php'));

        if ($current !== 'accept-terms.php' && $current !== 'logout.php') {
            $redirect = $current !== '' ? $current : 'dashboard.php';
            header('Location: accept-terms.php?redirect=' . urlencode($redirect));
            exit;
        }
    }
}

function get_site_content(string $key): string
{
    $stmt = db()->prepare("SELECT html_content FROM site_content WHERE key_name = ?");
    $stmt->execute([$key]);
    return (string)($stmt->fetchColumn() ?? '');
}
