<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

const CONVERSION_BATCH_SIZE = 5;
const STALE_PROCESSING_MINUTES = 30;

function reset_stale_tty_jobs(): void
{
    $sql = "
        UPDATE audio_files
        SET tty_status = 'pending',
            tty_error = CONCAT('Reset from stale processing at ', NOW()),
            updated_at = NOW(),
            tty_started_at = NULL,
            tty_completed_at = NULL
        WHERE is_deleted = 0
          AND tty_status = 'processing'
          AND tty_started_at < (NOW() - INTERVAL ? MINUTE)
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([STALE_PROCESSING_MINUTES]);
}
/**
 * Claim a batch safely using transaction + FOR UPDATE
 */
function claim_pending_audio_tty_batch(int $limit): array
{
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id
        FROM audio_files
        WHERE is_deleted = 0
          AND tty_status = 'pending'
          AND (
              NULLIF(TRIM(tty_transcription_text), '') IS NOT NULL
              OR transcription_status = 'complete'
          )
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
        SET tty_status = 'processing',
            tty_error = NULL,
            updated_at = NOW(),
            tty_started_at = NOW(),
            tty_completed_at = NULL,
            tty_attempts = tty_attempts + 1
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
function mark_audio_tty_complete(
    int $id, 
    string $ttyFilename,
    string $ttyRelativePath, 
    string $outputPath, 
    array $meta
): void
{
    $sql = "
        UPDATE audio_files
        SET
            tty_filename = ?,
            tty_relative_path = ?,
            tty_mime_type = ?,
            tty_file_ext = ?,
            tty_file_size_bytes = ?,
            tty_duration_seconds = ?,
            tty_audio_format = ?,
            tty_audio_type = ?,
            tty_channels = ?,
            tty_channel_mode = ?,
            tty_sample_rate_hz = ?,

            tty_status = 'complete',
            tty_error = NULL,
            updated_at = NOW(),
            tty_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);

    $stmt->execute([
        $ttyFilename,
        $ttyRelativePath,
        'audio/wav',
        'wav',
        is_file($outputPath) ? filesize($outputPath) : null,
        $meta['duration_seconds'] ?? null,
        $meta['audio_format'] ?? null,
        $meta['audio_type'] ?? null,
        $meta['channels'] ?? null,
        $meta['channel_mode'] ?? null,
        $meta['sample_rate_hz'] ?? null,
        $id
        ]);
}

/**
 * Mark failure
 */
function mark_audio_tty_failed(int $id, string $error): void
{
    $sql = "
        UPDATE audio_files
        SET
            tty_status = 'failed',
            tty_error = ?,
            updated_at = NOW(),
            tty_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([mb_substr($error, 0, 65535), $id]);
}
function mark_audio_tty_ignored(int $id): void
{
    $sql = "
        UPDATE audio_files
        SET
            tty_status = 'skipped',
            updated_at = NOW(),
            tty_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
}

function run_tty_worker(): void
{
    log_line('Starting tty worker');

    reset_stale_tty_jobs();

    $rows = claim_pending_audio_tty_batch(CONVERSION_BATCH_SIZE);

    if (!$rows) {
        log_line('No jobs found');
        return;
    }

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $customText = trim((string)($row['tty_transcription_text'] ?? ''));
        $text = $customText !== ''
            ? $customText
            : (string)($row['transcription_text'] ?? '');
        log_line("TTY ID {$id}: Processing");

        try {
            if(empty($text)) {
                log_line("TTY ID {$id}: Empty");
                mark_audio_tty_ignored($id);
            } else {
                $filePath = get_tty_path($row);
                $result = convert_text_for_tty($text, $filePath);
                if (!($result['success'] ?? false)) {
                    $message = $result['log'] ?? 'TTY conversion failed';
                    throw new RuntimeException($message);
                }                
                $meta = extract_audio_metadata($filePath, 'audio/wav', 'wav');

                $ttyFilename = tty_wav_filename((string)$row['stored_filename']);
                $ttyRelativePath = $row['user_id'] . '/' . $ttyFilename;

                mark_audio_tty_complete($id, $ttyFilename, $ttyRelativePath, $filePath, $meta);
                log_line("TTY ID {$id}: Complete");
            }
        } catch (Throwable $e) {
            mark_audio_tty_failed($id, $e->getMessage());
            log_line("TTY ID {$id}: Failed - {$e->getMessage()}");
        }
    }

    log_line('Done');
}

function get_tty_path(array $row): string {
  $userId  = $row['user_id'];
  $userDir =  audio_upload_dir_for_user((int)$userId);
  $fullPath = $userDir . DIRECTORY_SEPARATOR . tty_wav_filename((string)$row['stored_filename']);
  return $fullPath;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    run_tty_worker();
}
