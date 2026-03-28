<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
$error = '';
$success = flash_get('success') ?? '';

$isAjaxUpload = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function ajax_error_response(string $message, int $statusCode = 400): void
{
    global $isAjaxUpload;

    if ($isAjaxUpload) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!isset($_FILES['audio_file']) || !is_array($_FILES['audio_file'])) {
        $error = 'Please choose an audio file.';
    } else {
        $file = $_FILES['audio_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Upload failed. Please try again. '.upload_error_message((int)$file['error']);
            ajax_error_response($error, 415);
        } elseif (($file['size'] ?? 0) <= 0) {
            $error = 'The uploaded file is empty.';
            ajax_error_response($error, 415);
        } elseif (($file['size'] ?? 0) > MAX_AUDIO_UPLOAD_BYTES) {
            $error = 'The uploaded file exceeds the size limit.';
            ajax_error_response($error, 415);
        } else {
            $tmpPath = (string)$file['tmp_name'];
            $originalName = trim((string)$file['name']);
            $mimeType = detect_uploaded_mime_type($tmpPath);
            $allowed = allowed_audio_mime_types();
            if (!array_key_exists($mimeType, $allowed)) {
                $error = "Unsupported audio format: $mimeType";
                ajax_error_response($error, 415);
            } else {
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if ($ext === '') {
                    $ext = $allowed[$mimeType];
                }

                $userDir = audio_upload_dir_for_user((int)$user['id']);
                ensure_directory($userDir);

                $storedFilename = safe_upload_filename($originalName);
                $fullPath = $userDir . DIRECTORY_SEPARATOR . $storedFilename;

                if (!move_uploaded_file($tmpPath, $fullPath)) {
                    $error = 'Unable to store the uploaded file.';
                    ajax_error_response($error, 415);
                } else {
                    $originalMetadata = extract_audio_metadata($fullPath, $mimeType, $ext);

                    $conversionStatus = 'pending';

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
                        $conversionStatus,
                    ]);

                    flash_set('success', 'Audio uploaded successfully and queued for conversion.');
                    header('Location: audio-files.php');
                    exit;
                }
            }
        }
    }
}

html_header('Upload Audio');
?>
<h1>Upload audio</h1>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="audio_file">Audio file</label>
    <input id="audio_file" name="audio_file" type="file" accept="audio/*" required>

    <button type="submit">Upload</button>
</form>
<p><a href="audio-files.php">View uploaded audio</a></p>
<?php html_footer(); ?>
