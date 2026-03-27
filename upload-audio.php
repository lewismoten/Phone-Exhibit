<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$error = '';
$success = flash_get('success') ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (!isset($_FILES['audio_file']) || !is_array($_FILES['audio_file'])) {
        $error = 'Please choose an audio file.';
    } else {
        $file = $_FILES['audio_file'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Upload failed. Please try again.';
        } elseif (($file['size'] ?? 0) <= 0) {
            $error = 'The uploaded file is empty.';
        } elseif (($file['size'] ?? 0) > MAX_AUDIO_UPLOAD_BYTES) {
            $error = 'The uploaded file exceeds the size limit.';
        } else {
            $tmpPath = (string)$file['tmp_name'];
            $originalName = trim((string)$file['name']);
            $mimeType = detect_uploaded_mime_type($tmpPath);
            $allowed = allowed_audio_mime_types();
            if (!array_key_exists($mimeType, $allowed)) {
                $error = 'Unsupported audio format.';
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
                } else {
                    $metadata = extract_audio_metadata($fullPath, $mimeType, $ext);
                    $relativePath = $user['id'] . '/' . $storedFilename;

                    $stmt = db()->prepare(
                        'INSERT INTO audio_files (
                            user_id, original_filename, stored_filename, relative_path, mime_type, file_ext,
                            file_size_bytes, duration_seconds, audio_format, audio_type,
                            channels, channel_mode, sample_rate_hz
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );

                    $stmt->execute([
                        $user['id'],
                        $originalName,
                        $storedFilename,
                        $relativePath,
                        $mimeType,
                        $ext,
                        filesize($fullPath),
                        $metadata['duration_seconds'],
                        $metadata['audio_format'],
                        $metadata['audio_type'],
                        $metadata['channels'],
                        $metadata['channel_mode'],
                        $metadata['sample_rate_hz'],
                    ]);

                    flash_set('success', 'Audio uploaded successfully.');
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
