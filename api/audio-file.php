<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'POST') {
        save_audio_file($user);
        exit;
    }

    fetch_audio_file($user);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Server error while processing audio file.',
    ], JSON_THROW_ON_ERROR);
}

function fetch_audio_file(array $user): void
{
    $id = max(0, (int)($_POST['id'] ?? $_GET['id'] ?? 0));

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid audio file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM audio_files
         WHERE id = ?
           AND user_id = ?
           AND is_deleted = 0
         LIMIT 1"
    );

    $stmt->execute([$id, $user['id']]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Audio file not found.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    echo json_encode([
        'success' => true,
        'audio_file' => audio_file_payload($row),
    ], JSON_THROW_ON_ERROR);
}

function save_audio_file(array $user): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid security token.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $id = max(0, (int)($_POST['id'] ?? 0));

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid audio file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $directoryTitle = clean_nullable_text($_POST['directory_title'] ?? null, 150);
    $requestedPhoneNumber = clean_nullable_text($_POST['requested_phone_number'] ?? null, 32);
    $rolodexTitle = clean_nullable_text($_POST['rolodex_title'] ?? null, 150);
    $rolodexDetails = clean_nullable_text($_POST['rolodex_details'] ?? null, 5000);
    $ttyTranscriptionText = clean_nullable_text($_POST['tty_transcription_text'] ?? null, 20000);
    $aiTranscriptionOptIn = !empty($_POST['ai_transcription_opt_in']) ? 1 : 0;

    $stmt = db()->prepare(
        "UPDATE audio_files
         SET
            directory_title = ?,
            requested_phone_number = ?,
            rolodex_title = ?,
            rolodex_details = ?,
            tty_transcription_text = ?,
            ai_transcription_opt_in = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND user_id = ?
           AND is_deleted = 0"
    );

    $stmt->execute([
        $directoryTitle,
        $requestedPhoneNumber,
        $rolodexTitle,
        $rolodexDetails,
        $ttyTranscriptionText,
        $aiTranscriptionOptIn,
        $id,
        $user['id'],
    ]);

    if ($stmt->rowCount() < 1) {
        // Could mean no changes, so confirm ownership instead of treating it as failure.
        $check = db()->prepare(
            "SELECT id
             FROM audio_files
             WHERE id = ?
               AND user_id = ?
               AND is_deleted = 0
             LIMIT 1"
        );

        $check->execute([$id, $user['id']]);

        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Audio file not found.',
            ], JSON_THROW_ON_ERROR);
            return;
        }
    }

    $fetch = db()->prepare(
        "SELECT *
         FROM audio_files
         WHERE id = ?
           AND user_id = ?
           AND is_deleted = 0
         LIMIT 1"
    );

    $fetch->execute([$id, $user['id']]);
    $row = $fetch->fetch();

    echo json_encode([
        'success' => true,
        'audio_file' => audio_file_payload($row),
    ], JSON_THROW_ON_ERROR);
}

function audio_file_payload(array $row): array
{
    $hasConverted =
        !empty($row['converted_relative_path'])
        && (string)($row['conversion_status'] ?? '') === 'complete';

    if ($hasConverted) {
        $playbackUrl = converted_audio_playback_url($row);
        $playbackMimeType = $row['converted_mime_type'] ?: 'audio/wav';
    } elseif (!empty($row['relative_path'])) {
        $playbackUrl = original_audio_playback_url($row);
        $playbackMimeType = $row['mime_type'] ?: 'audio/mpeg';
    } else {
        $playbackUrl = null;
        $playbackMimeType = null;
    }

    return [
        'transcription_tty_preview' => !empty($row['transcription_text'])
            ? tty_format_text((string)$row['transcription_text'])
            : '',
        'id' => (int)$row['id'],
        'original_filename' => (string)($row['original_filename'] ?? ''),
        'short_name' => (string)($row['short_name'] ?? ''),
        'directory_title' => (string)($row['directory_title'] ?? ''),
        'requested_phone_number' => (string)($row['requested_phone_number'] ?? ''),
        'rolodex_title' => (string)($row['rolodex_title'] ?? ''),
        'rolodex_details' => (string)($row['rolodex_details'] ?? ''),
        'transcription_text' => (string)($row['transcription_text'] ?? ''),
        'tty_transcription_text' => (string)($row['tty_transcription_text'] ?? ''),
        'ai_transcription_opt_in' => (int)($row['ai_transcription_opt_in'] ?? 0),
        'transcription_status' => (string)($row['transcription_status'] ?? 'pending'),
        'conversion_status' => (string)($row['conversion_status'] ?? 'pending'),
        'playback_url' => $playbackUrl,
        'playback_mime_type' => $playbackMimeType,
        'using_converted_audio' => $hasConverted,
    ];
}

function clean_nullable_text(mixed $value, int $maxLength): ?string
{
    $text = trim((string)$value);

    if ($text === '') {
        return null;
    }

    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
    }

    return $text;
}
