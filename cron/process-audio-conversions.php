<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

const CONVERSION_BATCH_SIZE = 5;
const STALE_PROCESSING_MINUTES = 30;


/**
 * Reset stuck jobs (safety net)
 */
function reset_stale_audio_conversion_jobs(): void
{
    $sql = "
        UPDATE audio_files
        SET conversion_status = 'pending',
            conversion_error = CONCAT('Reset from stale processing at ', NOW()),
            updated_at = NOW(),
            conversion_started_at = NULL,
            conversion_completed_at = NULL
        WHERE is_deleted = 0
          AND conversion_status = 'processing'
          AND conversion_started_at < (NOW() - INTERVAL ? MINUTE)
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([STALE_PROCESSING_MINUTES]);
}

/**
 * Claim a batch safely using transaction + FOR UPDATE
 */
function claim_pending_audio_conversion_batch(int $limit): array
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

    $updateSql = "
        UPDATE audio_files
        SET conversion_status = 'processing',
            conversion_error = NULL,
            updated_at = NOW(),
            conversion_started_at = NOW(),
            conversion_completed_at = NULL,
            conversion_attempts = conversion_attempts + 1
        WHERE id IN ($placeholders)
    ";

    $update = $pdo->prepare($updateSql);
    $update->execute($ids);

    $fetch = $pdo->prepare("
        SELECT *
        FROM audio_files
        WHERE id IN ($placeholders)
        ORDER BY created_at ASC, id ASC
    ");
    $fetch->execute($ids);

    $rows = $fetch->fetchAll(PDO::FETCH_ASSOC);

    $pdo->commit();

    return $rows;
}

/**
 * Mark success
 */
function mark_audio_conversion_complete(int $id, array $data): void
{
    $sql = "
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
            updated_at = NOW(),
            conversion_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);

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
        $id,
    ]);
}

/**
 * Mark failure
 */
function mark_audio_conversion_failed(int $id, string $error): void
{
    $sql = "
        UPDATE audio_files
        SET
            conversion_status = 'failed',
            conversion_error = ?,
            updated_at = NOW(),
            conversion_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([mb_substr($error, 0, 65535), $id]);
}

/**
 * Process one file
 */
function process_audio_conversion_row(array $row): void
{
    $id = (int)$row['id'];

    $inputPath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . ltrim((string)$row['relative_path'], '/');

    if (!is_file($inputPath)) {
        throw new RuntimeException('Original file missing.');
    }

    $convertedFilename = converted_wav_filename((string)$row['stored_filename']);
    $convertedRelativePath = $row['user_id'] . '/' . $convertedFilename;

    $outputPath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . $convertedRelativePath;

    log_line("Processing audio conversion ID {$id}");

    $result = convert_audio_for_phone($inputPath, $outputPath);

    if (empty($result['success'])) {
        throw new RuntimeException($result['log'] ?: 'FFmpeg failed.');
    }

    $meta = extract_audio_metadata($outputPath, 'audio/wav', 'wav');

    mark_audio_conversion_complete($id, [
        'converted_filename' => $convertedFilename,
        'converted_relative_path' => $convertedRelativePath,
        'converted_mime_type' => 'audio/wav',
        'converted_file_ext' => 'wav',
        'converted_file_size_bytes' => is_file($outputPath) ? filesize($outputPath) : null,
        'converted_duration_seconds' => $meta['duration_seconds'] ?? null,
        'converted_audio_format' => $meta['audio_format'] ?? null,
        'converted_audio_type' => $meta['audio_type'] ?? null,
        'converted_channels' => $meta['channels'] ?? null,
        'converted_channel_mode' => $meta['channel_mode'] ?? null,
        'converted_sample_rate_hz' => $meta['sample_rate_hz'] ?? null,
    ]);

    if (!KEEP_ORIGINAL_AUDIO && is_file($inputPath)) {
        @unlink($inputPath);
    }

    log_line("Completed audio conversion ID {$id}");
}

/**
 * Run worker
 */
function run_audio_conversion_worker(): void
{
    log_line('Starting audio conversion worker');

    reset_stale_audio_conversion_jobs();

    $rows = claim_pending_audio_conversion_batch(CONVERSION_BATCH_SIZE);

    if (!$rows) {
        log_line('No audio conversion jobs found');
        return;
    }

    foreach ($rows as $row) {
        try {
            process_audio_conversion_row($row);
        } catch (Throwable $e) {
            mark_audio_conversion_failed((int)$row['id'], $e->getMessage());
            log_line("Failed audio conversion ID {$row['id']}: {$e->getMessage()}");
        }
    }

    log_line('Audio conversion worker done');
}

/**
 * Allow direct CLI execution, but do not kill parent scripts when included.
 */
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    run_audio_conversion_worker();
}