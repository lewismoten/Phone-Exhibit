<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

const CONVERSION_BATCH_SIZE = 5;
const STALE_PROCESSING_MINUTES = 30;

echo "[" . date('Y-m-d H:i:s') . "] Starting audio conversion worker...\n";

/**
 * Reset stuck jobs (safety net)
 */
function reset_stale_jobs(): void
{
    $stmt = db()->prepare("
        UPDATE audio_files
        SET conversion_status = 'pending',
            conversion_error = CONCAT('Reset from stale processing at ', NOW()),
            updated_at = NOW()
        WHERE conversion_status = 'processing'
          AND updated_at < (NOW() - INTERVAL ? MINUTE)
    ");
    $stmt->execute([STALE_PROCESSING_MINUTES]);
}

/**
 * Claim a batch safely using transaction + FOR UPDATE
 */
function claim_pending_batch(int $limit): array
{
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id
        FROM audio_files
        WHERE is_deleted = 0
          AND conversion_status = 'pending'
        ORDER BY created_at ASC, id ASC
        LIMIT ?
        FOR UPDATE
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$ids) {
        $pdo->commit();
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $update = $pdo->prepare("
        UPDATE audio_files
        SET conversion_status = 'processing',
            conversion_error = NULL,
            conversion_started_at = NOW(),
            conversion_attempts = conversion_attempts + 1,
            updated_at = NOW()
        WHERE id IN ($placeholders)
    ");
    $update->execute($ids);

    $fetch = $pdo->prepare("
        SELECT *
        FROM audio_files
        WHERE id IN ($placeholders)
    ");
    $fetch->execute($ids);

    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    return $rows;
}

/**
 * Mark success
 */
function mark_complete(int $id, array $data): void
{
    $stmt = db()->prepare("
        UPDATE audio_files
        SET
            converted_filename = ?,
            converted_relative_path = ?,
            converted_mime_type = ?,
            converted_file_ext = ?,
            converted_file_size_bytes = ?,
            converted_duration_seconds = ?,
            converted_audio_format = ?,
            converted_audio_type = ?,
            converted_channels = ?,
            converted_channel_mode = ?,
            converted_sample_rate_hz = ?,
            conversion_status = 'complete',
            conversion_error = NULL,
            conversion_completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $data['converted_filename'],
        $data['converted_relative_path'],
        $data['converted_mime_type'],
        $data['converted_file_ext'],
        $data['converted_file_size_bytes'],
        $data['converted_duration_seconds'],
        $data['converted_audio_format'],
        $data['converted_audio_type'],
        $data['converted_channels'],
        $data['converted_channel_mode'],
        $data['converted_sample_rate_hz'],
        $id
    ]);
}

/**
 * Mark failure
 */
function mark_failed(int $id, string $error): void
{
    $stmt = db()->prepare("
        UPDATE audio_files
        SET
            conversion_status = 'failed',
            conversion_error = ?,
            conversion_completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([mb_substr($error, 0, 65535), $id]);
}

/**
 * Process one file
 */
function process_row(array $row): void
{
    $id = (int)$row['id'];

    $inputPath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . ltrim($row['relative_path'], '/');

    if (!is_file($inputPath)) {
        throw new RuntimeException('Original file missing.');
    }

    $convertedFilename = converted_wav_filename($row['stored_filename']);
    $convertedRelativePath = $row['user_id'] . '/' . $convertedFilename;

    $outputPath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $convertedRelativePath;

    echo "Processing ID {$id}...\n";

    $result = convert_audio_for_phone($inputPath, $outputPath);

    if (!$result['success']) {
        throw new RuntimeException($result['log'] ?: 'FFmpeg failed.');
    }

    $meta = extract_audio_metadata($outputPath, 'audio/wav', 'wav');

    mark_complete($id, [
        'converted_filename' => $convertedFilename,
        'converted_relative_path' => $convertedRelativePath,
        'converted_mime_type' => 'audio/wav',
        'converted_file_ext' => 'wav',
        'converted_file_size_bytes' => filesize($outputPath),
        'converted_duration_seconds' => $meta['duration_seconds'],
        'converted_audio_format' => $meta['audio_format'],
        'converted_audio_type' => $meta['audio_type'],
        'converted_channels' => $meta['channels'],
        'converted_channel_mode' => $meta['channel_mode'],
        'converted_sample_rate_hz' => $meta['sample_rate_hz'],
    ]);

    if (!KEEP_ORIGINAL_AUDIO && is_file($inputPath)) {
        @unlink($inputPath);
    }

    echo "Completed ID {$id}\n";
}

/**
 * Run worker
 */
reset_stale_jobs();

$rows = claim_pending_batch(CONVERSION_BATCH_SIZE);

if (!$rows) {
    echo "No jobs found.\n";
    exit(0);
}

foreach ($rows as $row) {
    try {
        process_row($row);
    } catch (Throwable $e) {
        mark_failed((int)$row['id'], $e->getMessage());
        echo "Failed ID {$row['id']}: {$e->getMessage()}\n";
    }
}

echo "Done.\n";