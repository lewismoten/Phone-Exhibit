<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$requestedUserId = trim((string)($_GET['user_id'] ?? 'all'));
$perPage = 10;
$offset = pagination_offset($page, $perPage);

$where = 'WHERE af.is_deleted = 0';
$params = [];

if (!is_admin()) {
    $where .= ' AND af.user_id = ?';
    $params[] = $user['id'];
} elseif ($requestedUserId !== '' && $requestedUserId !== 'all') {
    $filterUserId = (int)$requestedUserId;
    if ($filterUserId > 0) {
        $where .= ' AND af.user_id = ?';
        $params[] = $filterUserId;
    }
}

if ($q !== '') {
    $where .= ' AND (
        af.original_filename LIKE ?
        OR af.short_name LIKE ?
        OR af.directory_title LIKE ?
        OR af.rolodex_title LIKE ?
        OR af.exhibit_phone_number LIKE ?
        OR af.requested_phone_number LIKE ?
        OR af.transcription_text LIKE ?
        OR af.tty_transcription_text LIKE ?
    )';

    $like = '%' . $q . '%';

    array_push(
        $params,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM audio_files af $where");
$countStmt->execute($params);

$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$listStmt = db()->prepare(
    "SELECT
        af.id,
        af.original_filename,
        af.short_name,
        af.directory_title,
        af.rolodex_title,
        af.exhibit_phone_number,
        af.requested_phone_number,
        af.conversion_status,
        af.converted_relative_path,
        af.converted_mime_type,
        af.relative_path,
        af.mime_type,
        af.created_at,
        af.paper_classification_code,
        dpc.label AS paper_classification_label,
        dpc.color_hex AS paper_classification_color,
        dpc.description AS paper_classification_description
     FROM audio_files
     af
     LEFT JOIN directory_paper_classifications dpc
       ON dpc.code = af.paper_classification_code
     $where
     ORDER BY af.created_at DESC, af.id DESC
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

    $phoneStatus = $row['exhibit_phone_number']
        ? 'assigned'
        : ($row['requested_phone_number'] ? 'requested' : 'unassigned');

    $out[] = [
        'id' => (int)$row['id'],
        'title' => (string)$title,
        'original_filename' => (string)($row['original_filename'] ?? ''),
        'phone_number' => $phoneNumber,
        'phone_status' => $phoneStatus,
        'paper_classification_code' => (string)($row['paper_classification_code'] ?? ''),
        'paper_classification_label' => (string)($row['paper_classification_label'] ?? ''),
        'paper_classification_color' => (string)($row['paper_classification_color'] ?? ''),
        'paper_classification_description' => (string)($row['paper_classification_description'] ?? ''),
        'conversion_status' => (string)($row['conversion_status'] ?? 'pending'),
        'playback_url' => $playbackUrl,
        'playback_mime_type' => $playbackMimeType,
        'using_converted_audio' => $hasConverted,
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
