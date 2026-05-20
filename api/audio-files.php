<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = pagination_offset($page, $perPage);

$where = 'WHERE user_id = ? AND is_deleted = 0';
$params = [$user['id']];

if ($q !== '') {
    $where .= ' AND (
        original_filename LIKE ?
        OR short_name LIKE ?
        OR directory_title LIKE ?
        OR rolodex_title LIKE ?
        OR transcription_text LIKE ?
        OR tty_transcription_text LIKE ?
    )';

    $like = '%' . $q . '%';

    array_push(
        $params,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM audio_files $where");
$countStmt->execute($params);

$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$listStmt = db()->prepare(
    "SELECT
        id,
        original_filename,
        short_name,
        directory_title,
        rolodex_title,
        exhibit_phone_number,
        requested_phone_number,
        conversion_status,
        converted_relative_path,
        converted_mime_type,
        relative_path,
        mime_type,
        created_at
     FROM audio_files
     $where
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);

$listStmt->execute($listParams);
$rows = $listStmt->fetchAll();

$out = [];

foreach ($rows as $row) {
    $hasConverted =
        !empty($row['converted_relative_path'])
        && ($row['conversion_status'] ?? '') === 'complete';

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

    $title =
        $row['directory_title']
        ?: $row['short_name']
        ?: $row['original_filename'];

    $phoneNumber =
        $row['exhibit_phone_number']
        ?: $row['requested_phone_number']
        ?: null;

    $out[] = [
        'id' => (int)$row['id'],
        'title' => (string)$title,
        'phone_number' => $phoneNumber,
        'conversion_status' => (string)($row['conversion_status'] ?? 'pending'),
        'playback_url' => $playbackUrl,
        'playback_mime_type' => $playbackMimeType,
        'created_at' => (string)$row['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'rows' => $out,
    'page' => $page,
    'per_page' => $perPage,
    'total_rows' => $totalRows,
    'total_pages' => $totalPages,
], JSON_THROW_ON_ERROR);