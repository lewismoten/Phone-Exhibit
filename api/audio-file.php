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
    error_log(sprintf(
        'Audio file API error: %s in %s on line %d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

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

    $row = find_editable_audio_file($id, $user);

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
        'paper_classifications' => is_admin() ? paper_classification_options() : [],
        'can_edit_paper_classification' => is_admin(),
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
    $requestedPhoneNumber = clean_requested_phone_number($_POST['requested_phone_number'] ?? null);
    $rolodexTitle = clean_rolodex_title_text($_POST['rolodex_title'] ?? null);
    $rolodexDetails = clean_rolodex_details_text($_POST['rolodex_details'] ?? null);
    $ttyTranscriptionText = clean_tty_transcription_text($_POST['tty_transcription_text'] ?? null, 20000);
    $aiTranscriptionOptIn = !empty($_POST['ai_transcription_opt_in']) ? 1 : 0;

    $row = find_editable_audio_file($id, $user);

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Audio file not found.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $paperClassificationCode = is_admin()
        ? clean_paper_classification_code($_POST['paper_classification_code'] ?? null)
        : (($row['paper_classification_code'] ?? null) !== null ? (string)$row['paper_classification_code'] : null);

    $paperClassificationWasChanged = is_admin()
        && $paperClassificationCode !== (($row['paper_classification_code'] ?? null) !== null ? (string)$row['paper_classification_code'] : null);
    $sharedAssignedNumber = trim((string)($row['exhibit_phone_number'] ?? ''));
    $ttyTranscriptWasChanged = $ttyTranscriptionText !== ($row['tty_transcription_text'] ?? null);
    $ttyTranscriptChangedFlag = $ttyTranscriptWasChanged ? 1 : 0;

    $stmt = db()->prepare(
        "UPDATE audio_files
         SET
            directory_title = ?,
            requested_phone_number = ?,
            paper_classification_code = ?,
            rolodex_title = ?,
            rolodex_details = ?,
            tty_transcription_text = ?,
            tty_status = CASE WHEN ? THEN 'pending' ELSE tty_status END,
            tty_error = CASE WHEN ? THEN NULL ELSE tty_error END,
            tty_started_at = CASE WHEN ? THEN NULL ELSE tty_started_at END,
            tty_completed_at = CASE WHEN ? THEN NULL ELSE tty_completed_at END,
            ai_transcription_opt_in = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND is_deleted = 0"
    );

    $stmt->execute([
        $directoryTitle,
        $requestedPhoneNumber,
        $paperClassificationCode,
        $rolodexTitle,
        $rolodexDetails,
        $ttyTranscriptionText,
        $ttyTranscriptChangedFlag,
        $ttyTranscriptChangedFlag,
        $ttyTranscriptChangedFlag,
        $ttyTranscriptChangedFlag,
        $aiTranscriptionOptIn,
        $id,
    ]);

    if ($paperClassificationWasChanged && $sharedAssignedNumber !== '') {
        $sharedNumberStatement = db()->prepare(
            "UPDATE audio_files
             SET
                paper_classification_code = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE exhibit_phone_number = ?
               AND is_deleted = 0"
        );
        $sharedNumberStatement->execute([
            $paperClassificationCode,
            $sharedAssignedNumber,
        ]);
    }

    if ($stmt->rowCount() < 1) {
        $check = find_editable_audio_file($id, $user);

        if (!$check) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Audio file not found.',
            ], JSON_THROW_ON_ERROR);
            return;
        }
    }

    $row = find_editable_audio_file($id, $user);

    echo json_encode([
        'success' => true,
        'audio_file' => audio_file_payload($row),
    ], JSON_THROW_ON_ERROR);
}

function find_editable_audio_file(int $id, array $user): ?array
{
    if ($id <= 0) {
        return null;
    }

    if (is_admin()) {
        $stmt = db()->prepare(
            "SELECT *
             FROM audio_files
             WHERE id = ?
               AND is_deleted = 0
             LIMIT 1"
        );
        $stmt->execute([$id]);
    } else {
        $stmt = db()->prepare(
            "SELECT *
             FROM audio_files
             WHERE id = ?
               AND user_id = ?
               AND is_deleted = 0
             LIMIT 1"
        );
        $stmt->execute([$id, $user['id']]);
    }

    $row = $stmt->fetch();

    return $row ?: null;
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
        'paper_classification_code' => (string)($row['paper_classification_code'] ?? ''),
        'requested_phone_number' => (string)($row['requested_phone_number'] ?? ''),
        'exhibit_phone_number' => (string)($row['exhibit_phone_number'] ?? ''),
        'tty_phone_number' => (string)($row['tty_phone_number'] ?? ''),
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

function paper_classification_options(): array
{
    $statement = db()->query(
        'SELECT code, label, description, color_hex
         FROM directory_paper_classifications
         WHERE is_active = 1
         ORDER BY sort_order ASC, code ASC'
    );

    $options = [];

    foreach ($statement->fetchAll() as $row) {
        $options[] = [
            'code' => (string)($row['code'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'color_hex' => (string)($row['color_hex'] ?? ''),
        ];
    }

    return $options;
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

function clean_requested_phone_number(mixed $value): ?string
{
    $text = strtoupper((string)$value);
    $map = [
        'A' => '2', 'B' => '2', 'C' => '2',
        'D' => '3', 'E' => '3', 'F' => '3',
        'G' => '4', 'H' => '4', 'I' => '4',
        'J' => '5', 'K' => '5', 'L' => '5',
        'M' => '6', 'N' => '6', 'O' => '6',
        'P' => '7', 'Q' => '7', 'R' => '7', 'S' => '7',
        'T' => '8', 'U' => '8', 'V' => '8',
        'W' => '9', 'X' => '9', 'Y' => '9', 'Z' => '9',
    ];

    $digits = '';
    foreach (mb_str_split($text) as $char) {
        if ($char >= '0' && $char <= '9') {
            $digits .= $char;
            continue;
        }

        if (isset($map[$char])) {
            $digits .= $map[$char];
        }
    }

    $digits = substr($digits, 0, 20);

    return $digits === '' ? null : $digits;
}

function clean_paper_classification_code(mixed $value): ?string
{
    $code = strtoupper(trim((string)$value));

    if ($code === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT code
         FROM directory_paper_classifications
         WHERE code = ?
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute([$code]);

    $found = $statement->fetchColumn();

    return $found === false ? null : (string)$found;
}

function clean_tty_transcription_text(mixed $value, int $maxLength): ?string
{
    $text = tty_format_manual_text(trim((string)$value));

    if ($text === '') {
        return null;
    }

    if (mb_strlen($text) > $maxLength) {
        $text = mb_substr($text, 0, $maxLength);
    }

    return $text;
}

function clean_rolodex_title_text(mixed $value): ?string
{
    $text = normalize_rolodex_text((string)$value);
    $text = preg_replace('/\s+/', ' ', str_replace("\n", ' ', $text));
    $text = trim((string)$text);

    if ($text === '') {
        return null;
    }

    return mb_substr($text, 0, 40);
}

function clean_rolodex_details_text(mixed $value): ?string
{
    $text = normalize_rolodex_text((string)$value);
    $wrappedLines = [];

    foreach (explode("\n", $text) as $line) {
        $wrappedLines = array_merge($wrappedLines, wrap_rolodex_line($line, 40));
    }

    $wrappedLines = array_slice($wrappedLines, 0, 5);
    $wrappedLines = array_map(
        static fn (string $line): string => rtrim($line),
        $wrappedLines
    );

    while (!empty($wrappedLines) && end($wrappedLines) === '') {
        array_pop($wrappedLines);
    }

    $normalized = trim(implode("\n", $wrappedLines));

    return $normalized === '' ? null : $normalized;
}

function normalize_rolodex_text(string $value): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $value);
    $text = str_replace("\t", ' ', $text);
    $text = str_replace(['1/2', '1/4'], ['½', '¼'], $text);
    $text = preg_replace('/[^A-Za-z0123456789 \n,\.\?!:;\'&\-()@¢£½¼]/u', '', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", (string)$text);

    return (string)$text;
}

function wrap_rolodex_line(string $line, int $width): array
{
    $trimmed = ltrim(rtrim($line), ' ');

    if ($trimmed === '') {
        return [''];
    }

    $words = preg_split('/ +/', $trimmed) ?: [];
    $wrapped = [];
    $current = '';

    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }

        if (mb_strlen($word) > $width) {
            if ($current !== '') {
                $wrapped[] = $current;
                $current = '';
            }

            for ($i = 0; $i < mb_strlen($word); $i += $width) {
                $wrapped[] = mb_substr($word, $i, $width);
            }
            continue;
        }

        $candidate = $current === '' ? $word : $current . ' ' . $word;
        if (mb_strlen($candidate) <= $width) {
            $current = $candidate;
            continue;
        }

        $wrapped[] = $current;
        $current = $word;
    }

    if ($current !== '') {
        $wrapped[] = $current;
    }

    return $wrapped === [] ? [''] : $wrapped;
}
