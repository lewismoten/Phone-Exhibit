<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

const CONVERSION_BATCH_SIZE = 5;
const STALE_PROCESSING_MINUTES = 30;

function reset_stale_transcription_jobs(): void
{
    $sql = "
        UPDATE audio_files
        SET transcription_status = 'pending',
            transcription_error = CONCAT('Reset from stale processing at ', NOW()),
            updated_at = NOW(),
            transcription_started_at = NULL,
            transcription_completed_at = NULL
        WHERE is_deleted = 0
          AND transcription_status = 'processing'
          AND transcription_started_at < (NOW() - INTERVAL ? MINUTE)
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([STALE_PROCESSING_MINUTES]);
}
/**
 * Claim a batch safely using transaction + FOR UPDATE
 */
function claim_pending_audio_transcription_batch(int $limit): array
{
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id
        FROM audio_files
        WHERE is_deleted = 0
          AND transcription_status = 'pending'
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
        SET transcription_status = 'processing',
            transcription_error = NULL,
            updated_at = NOW(),
            transcription_started_at = NOW(),
            transcription_completed_at = NULL,
            transcription_attempts = transcription_attempts + 1
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
function mark_audio_transcription_complete(int $id, string $text): void
{
    $sql = "
        UPDATE audio_files
        SET
            transcription_status = 'complete',
            transcription_error = NULL,
            updated_at = NOW(),
            transcription_completed_at = NOW(),
            transcription_text = ?
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);

    $stmt->execute([
        $text,
        $id,
    ]);
}

/**
 * Mark failure
 */
function mark_audio_transcription_failed(int $id, string $error): void
{
    $sql = "
        UPDATE audio_files
        SET
            transcription_status = 'failed',
            transcription_error = ?,
            updated_at = NOW(),
            transcription_completed_at = NOW()
        WHERE id = ?
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute([mb_substr($error, 0, 65535), $id]);
}

function run_transcription_worker(): void
{
    log_line('Starting transcription worker');

    reset_stale_transcription_jobs();

    $rows = claim_pending_audio_transcription_batch(CONVERSION_BATCH_SIZE);

    if (!$rows) {
        log_line('No jobs found');
        return;
    }

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $seconds = $row['duration_seconds'];
        $cost = ($seconds / 60) * OPENAI_TRANSCRIPTION_COST_PER_MINUTE;

        try {
          // userid and stored file name

            $filePath = get_original_path($row);

            log_line("Transcribing ID {$id}");

            $text = transcribe_with_api($filePath);

            mark_audio_transcription_complete($id, $text);

            log_line("Transcribed {$seconds}s for ID {$id} (~\${$cost})");
        } catch (Throwable $e) {
            mark_audio_transcription_failed($id, $e->getMessage());
            log_line("Failed ID {$id}: {$e->getMessage()}");
        }
    }

    log_line('Done');
}

function get_original_path(array $row): string {
  $userId  = $row['user_id'];
  $storedFilename = $row['stored_filename'];
  $userDir =  audio_upload_dir_for_user((int)$userId);
  $fullPath = $userDir . DIRECTORY_SEPARATOR . $storedFilename;
  return $fullPath;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    run_transcription_worker();
}