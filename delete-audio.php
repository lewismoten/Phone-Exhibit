<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: audio-files.php');
    exit;
}

verify_csrf();
$user = current_user();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    flash_set('error', 'Invalid file selection.');
    header('Location: audio-files.php');
    exit;
}

$row = get_audio_file_for_user($id, (int)$user['id']);
if (!$row) {
    flash_set('error', 'Audio file not found.');
    header('Location: audio-files.php');
    exit;
}

$fullPath = rtrim(UPLOAD_BASE_DIR, '/\\') . DIRECTORY_SEPARATOR . $row['relative_path'];
if (is_file($fullPath)) {
    @unlink($fullPath);
}

if (!empty($row['converted_relative_path'])) {
    $convertedFullPath = rtrim(UPLOAD_BASE_DIR, '/\\') . DIRECTORY_SEPARATOR . $row['converted_relative_path'];
    if (is_file($convertedFullPath)) {
        @unlink($convertedFullPath);
    }
}

$stmt = db()->prepare('UPDATE audio_files SET is_deleted = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $user['id']]);

flash_set('success', 'Audio file deleted.');
header('Location: audio-files.php');
exit;