<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/getid3/getid3.php';

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

function login_user(int $userId, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
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

function html_border_pieces(): void 
{
    echo '<div class="border-piece border-top"></div>';
    echo '<div class="border-piece border-top-center-ornament"></div>';
    echo '<div class="border-piece border-bottom"></div>';
    echo '<div class="border-piece border-left"></div>';
    echo '<div class="border-piece border-right"></div>';
    echo '<div class="border-piece border-corner border-top-left"></div>';
    echo '<div class="border-piece border-corner border-top-right"></div>';
    echo '<div class="border-piece border-corner border-bottom-left"></div>';
    echo '<div class="border-piece border-corner border-bottom-right"></div>';
}
function html_a(string $href, string $text, string $icon): void
{
    echo '<a href="'.$href.'">';
    if(!empty($icon)) {
        html_span("icon $icon");
    }
    echo $text.'</a>';

}
function html_span(string $name, string $content = ''): void
{
    echo '<span class="'.$name.'">'.$content.'</span>';
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
    echo '<div class="page">';
    html_border_pieces();
    echo '<div class="masthead">';
    echo '<div class="left"></div>';
    echo '<div class="title"></div>';
    echo '<div class="right"></div>';
    echo '</div>'; // .page .masthead

    // Top Navigation
    echo '<nav class="topnav" aria-label="Main navigation">';
    echo '<div class="topnav-left"></div>';
    echo '<div class="main">';
    html_a('/', 'Home', 'home');
    if (current_user()) {
        html_span('separator');
        html_a('/dashboard.php', 'Dashboard', 'gauge');
        if(is_admin()) {
            html_span('separator');
            html_a('/tty-message.php', 'TTY', 'typewriter');
            html_span('separator');
            html_a('/qr-code.php', 'QR', 'qr');
        }
        html_span('separator');
        html_a('/phone-directory.php', 'Directory', 'book');
        html_span('separator');
        html_a('/legal.php', 'Legal Notice', 'scales');
        html_span('separator');
        html_a('/change-password.php', 'Change Password', 'key');
        echo '</div>'; // .topnav .topnav-main

        echo '<div class="topnav-utility-left"></div>';
        echo '<div class="topnav-utility">';
        html_a('/logout.php', 'Logout', 'exit');
        echo '<div class="topnav-utility-right"></div>';
    } else {
        html_span('separator');
        html_a('/register.php', 'Register', 'add-person');
        html_span('separator');
        html_a('/phone-directory.php', 'Directory', 'book');
        html_span('separator');
        html_a('/legal.php', 'Legal Notice', 'scales');
        echo '</div>'; // .page .topnav .topnav-main

        echo '<div class="topnav-utility-left"></div>';
        echo '<div class="topnav-utility">';
        html_a('/login.php', 'Login', 'lock');
        echo '</div>'; // .page .topnav .topnav-utility
        echo '<div class="topnav-utility-right"></div>';
    }
    echo '</nav>'; // .page .topnav

    echo '<div class="page-content">';
    
    echo '<div class="card primary">';
    html_border_pieces();

    echo '<section class="hero-marquee">';
    echo '<div class="panel">';

    echo '<div class="columns">';
    echo '<div class="center">';
    echo '<h1>Preserve Voices.</h1><h1>Share History.</h1>';
    echo '<hr>';
    echo '<p>';
    echo 'Dial in from any exhibit phone to leave a message,<br>';
    echo 'listen to stories, and experience the past<br>';
    echo 'through the sound of a simpler time.';
    echo '</p>';
    echo '<a class="button" href="#">';
    echo '<span class="icon microphone"></span>';
    echo 'Learn how it works</a>'; // .page .card-primary .hero-marquee .panel .columns .button
    echo '</div>'; // .page .card-primary .hero-marquee .panel .columns .center
    echo '<div class="hero-marquee-1"></div>';
    echo '</div>'; // .page .card-primary .hero-marquee .panel .columns

    echo '</div>'; // .page .card-primary .hero-marquee .panel
    echo '</section>'; // .page .card-primary .hero-marquee

    echo '</div>'; // .page .card.primary

    echo '<div class="card secondary">';
    html_border_pieces();
    echo '<h1 class="center">';
    html_span("icon microphone");
    echo 'Recent Contributions</h1>';
    echo '<hr>';
    html_span('bullet-item dot-leader', 'item 1');
    html_span('bullet-item dot-leader', 'item 2');
    html_span('bullet-item dot-leader', 'item 3');
    html_span('bullet-item dot-leader', 'item 4');
    html_span('bullet-item dot-leader', 'item 5');
    html_span('bullet-item dot-leader', 'item 6');
    html_span('bullet-item dot-leader', 'item 7');
    html_span('bullet-item dot-leader', 'item 8');
    html_span('bullet-item dot-leader', 'item 9');
    html_span('bullet-item dot-leader', 'item 10');

    echo "Lorem Ipsum";
    echo '<hr class="split">';
    echo "Lorem Ipsum";
    echo '</div>'; // .page .card.secondary

}

function html_footer(): void
{
    echo '<hr>';
    echo '</div>';// .page.page-content
    echo '</div>';// .page
    echo '<div class="footer">';
    echo '<div class="left">';
    echo 'Front Royal, Virginia';
    echo '</div>'; // .footer.left
    echo '<div class="left">';
    echo '<div class="center">';
    echo '-o-';
    echo '</div>'; // .footer.center
    echo 'Preserving our Past. Inspiring our future.';
    echo '<a href="/Restoring The Signal Info.pdf">Info Flyer</a>';
    echo '</div>'; // .footer.right
    echo '</div>'; // .footer
    echo '</body>';
    echo '</html>';

}

function send_password_reset_email(string $email, string $resetUrl): void
{
    $body = "A password reset was requested for your account.\n\n" .
            "Use this link to reset your password:\n" .
            $resetUrl . "\n\n" .
            "If you did not request this, you can ignore this email.";

    send_email($email, 'Password Reset', $body);
}
function send_email(string $email, string $subject, string $body): void {
    
    $url = 'https://api.mailgun.net/v3/'.MAILGUN_DOMAIN.'/messages';

    $data = [
        'from' => MAIL_FROM_NAME.' <'.MAILGUN_LOCAL_PART.'@'.MAILGUN_DOMAIN.'>',
        'to' => $email,
        'subject' => APP_NAME.': '.$subject,
        'text' => $body
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => "api:" . MAILGUN_APIKEY,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 10,
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        error_log('Email cURL error: '.curl_error($ch));
    } else {
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log('Email success: ' . $result);
        } else {
            error_log('Mailgun error (' . $httpCode . '): ' . $result);
        }
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
function tty_audio_playback_url(array $row): ?string
{
    $storedFilename = trim((string)($row['stored_filename'] ?? ''));
    $userId = (int)($row['user_id'] ?? 0);

    if ($storedFilename === '' || $userId <= 0) {
        return null;
    }

    $ttyFilename = tty_wav_filename($storedFilename);
    $relativePath = $userId . '/' . $ttyFilename;

    return upload_file_url($relativePath);
}
function upload_file_url(string $relativePath): string
{
    return '/uploads/audio/' . ltrim($relativePath, '/');
}
function get_minimodem_path(): ?string
{
    if (defined('MINIMODEM_BIN') && MINIMODEM_BIN) {
        if (is_file(MINIMODEM_BIN) && is_executable(MINIMODEM_BIN)) {
            return MINIMODEM_BIN;
        }
    }

    $output = [];
    $exitCode = 0;

    exec('command -v minimodem 2>/dev/null', $output, $exitCode);

    return ($exitCode === 0 && !empty($output[0])) ? trim($output[0]) : null;
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
function tty_wav_filename(string $storedFilename): string
{
    $base = pathinfo($storedFilename, PATHINFO_FILENAME);
    return $base . '.tty.wav';
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

function tty_wrap(string $text, int $width = 32): string
{
    $lines = explode("\n", $text);
    $wrapped = [];

    foreach ($lines as $line) {
        $wrapped[] = wordwrap($line, $width, "\n", true);
    }

    return implode("\n", $wrapped);
}

function convert_text_for_tty(string $text, string $outputPath, bool $raw = false): array
{
    $bin = get_minimodem_path();
    if (!$bin) {
        throw new RuntimeException('minimodem not found');
    }

    $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $text) ?? '';

    if (!$raw) {
        $text = trim(preg_replace('/[^A-Z0-9]+/', ' ', strtoupper($text)) ?? '');
    }

    $text = tty_wrap($text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text .= "\n";

    $tmpFile = tempnam(sys_get_temp_dir(), 'tty_txt_');
    if ($tmpFile === false) {
        throw new RuntimeException('Unable to create temporary text file.');
    }

    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
        throw new RuntimeException('Unable to create output directory.');
    }

    try {
        if (file_put_contents($tmpFile, $text) === false) {
            throw new RuntimeException('Unable to write temporary text file.');
        }

        $cmd1 = sprintf(
            '%s --tx tdd -R 8000 -f %s < %s 2>&1',
            escapeshellarg($bin),
            escapeshellarg($outputPath),
            escapeshellarg($tmpFile)
        );

        $output1 = [];
        $exitCode1 = 0;
        exec($cmd1, $output1, $exitCode1);

        $success = (
            $exitCode1 === 0 &&
            is_file($outputPath) &&
            filesize($outputPath) > 0
        );

        return [
            'success' => $success,
            'exit_code' => $exitCode1,
            'log' => implode("\n", $output1),
            'command' => $cmd1,
        ];

    } finally {
        if (is_file($tmpFile)) {
            unlink($tmpFile);
        }
    }
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

function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_OK => 'No upload error.',
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server\'s upload_max_filesize limit of '.ini_get('upload_max_filesize').'.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form MAX_FILE_SIZE limit.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'The server failed to write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        default => 'Unknown upload error.',
    };
}

function transcribe_with_api(string $filePath): string
{
    if (!is_file($filePath)) {
        throw new RuntimeException('Audio file not found for transcription.');
    }

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($filePath),
            'model' => OPENAI_TRANSCRIPTION_MODEL
        ],
        CURLOPT_TIMEOUT => 300,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        throw new RuntimeException('cURL error: ' . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        throw new RuntimeException("API HTTP {$status}: {$response}");
    }

    $data = json_decode($response, true);

    if (!isset($data['text'])) {
        throw new RuntimeException('Invalid API response.');
    }

    return $data['text'];
}
function log_line(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}
function original_audio_playback_url(array $row): ?string
{
    $relativePath = trim((string)($row['relative_path'] ?? ''));
    if ($relativePath === '') {
        return null;
    }

    return rtrim(UPLOAD_BASE_URL, '/') . '/' . ltrim($relativePath, '/');
}

function converted_audio_playback_url(array $row): ?string
{
    $relativePath = trim((string)($row['converted_relative_path'] ?? ''));
    if ($relativePath === '') {
        return null;
    }

    return rtrim(UPLOAD_BASE_URL, '/') . '/' . ltrim($relativePath, '/');
}

function is_admin(): bool {
    return $_SESSION['role'] === 'admin';
}
function require_admin(): void
{
    if (empty($_SESSION['user_id'])) {
        http_response_code(403);
        exit('Login required.');
    }
    if(!is_admin()) {
        http_response_code(403);
        exit('Administrator access required.');
    }
}