<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = db()->prepare(
        "SELECT
            id,
            coalesce(
                nullif(directory_title, ''),
                nullif(rolodex_title, ''),
                original_filename
            ) AS title,
            relative_path,
            mime_type,
            converted_relative_path,
            converted_mime_type,
            conversion_status,
            created_at AS date
         FROM audio_files
         WHERE is_deleted = 0
         ORDER BY created_at DESC, id DESC
         LIMIT 5"
    );

    $stmt->execute([]);
    $rows = $stmt->fetchAll();
    $entries = [];

    foreach ($rows as $row) {
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

        $entries[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'date' => (string)$row['date'],
            'playback_url' => $playbackUrl,
            'playback_mime_type' => $playbackMimeType,
        ];
    }

    echo json_encode([
        'success' => true,
        'entries' => $entries,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Unable to load recent audio entries.',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
