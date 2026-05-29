<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

try {
    upload_audio_file($user);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Server error while uploading audio file.',
    ], JSON_THROW_ON_ERROR);
}

function upload_audio_file(array $user): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid security token.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    if (!isset($_FILES['audio_file']) || !is_array($_FILES['audio_file'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Please choose an audio file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $file = $_FILES['audio_file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(415);
        echo json_encode([
            'success' => false,
            'error' => 'Upload failed. Please try again. ' . upload_error_message((int) $file['error']),
        ], JSON_THROW_ON_ERROR);
        return;
    }

    if (($file['size'] ?? 0) <= 0) {
        http_response_code(415);
        echo json_encode([
            'success' => false,
            'error' => 'The uploaded file is empty.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    if (($file['size'] ?? 0) > MAX_AUDIO_UPLOAD_BYTES) {
        http_response_code(415);
        echo json_encode([
            'success' => false,
            'error' => 'The uploaded file exceeds the size limit.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $tmpPath = (string) $file['tmp_name'];
    $originalName = trim((string) $file['name']);
    $mimeType = detect_uploaded_mime_type($tmpPath);
    $allowed = allowed_audio_mime_types();

    if (!array_key_exists($mimeType, $allowed)) {
        http_response_code(415);
        echo json_encode([
            'success' => false,
            'error' => "Unsupported audio format: {$mimeType}",
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = $allowed[$mimeType];
    }

    $userDir = audio_upload_dir_for_user((int) $user['id']);
    ensure_directory($userDir);

    $storedFilename = safe_upload_filename($originalName);
    $fullPath = $userDir . DIRECTORY_SEPARATOR . $storedFilename;

    if (!move_uploaded_file($tmpPath, $fullPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Unable to store the uploaded file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $originalMetadata = extract_audio_metadata($fullPath, $mimeType, $ext);
    $relativePath = $user['id'] . '/' . $storedFilename;

    $stmt = db()->prepare(
        'INSERT INTO audio_files (
            user_id,
            original_filename,
            stored_filename,
            relative_path,
            mime_type,
            file_ext,
            file_size_bytes,
            duration_seconds,
            audio_format,
            audio_type,
            channels,
            channel_mode,
            sample_rate_hz,
            conversion_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        $user['id'],
        $originalName,
        $storedFilename,
        $relativePath,
        $mimeType,
        $ext,
        filesize($fullPath),
        $originalMetadata['duration_seconds'],
        $originalMetadata['audio_format'],
        $originalMetadata['audio_type'],
        $originalMetadata['channels'],
        $originalMetadata['channel_mode'],
        $originalMetadata['sample_rate_hz'],
        'pending',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Audio uploaded successfully and queued for conversion.',
        'audio_file_id' => (int) db()->lastInsertId(),
    ], JSON_THROW_ON_ERROR);
}
