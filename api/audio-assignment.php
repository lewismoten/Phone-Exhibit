<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Only administrators can manage audio assignment.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method === 'POST') {
        save_audio_assignment();
        exit;
    }

    fetch_audio_assignment();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to manage audio assignment.',
    ], JSON_THROW_ON_ERROR);
}

function fetch_audio_assignment(): void
{
    $id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid audio file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $row = find_assignment_audio_file($id);

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
        'audio_file' => assignment_audio_payload($row),
        'paper_classifications' => paper_classification_assignment_options(),
    ], JSON_THROW_ON_ERROR);
}

function save_audio_assignment(): void
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

    $row = find_assignment_audio_file($id);

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Audio file not found.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $requestedPhoneNumber = clean_assignment_phone_number($row['requested_phone_number'] ?? null);
    $acceptRequestedNumber = !empty($_POST['accept_requested_number']);
    $exhibitPhoneNumber = $acceptRequestedNumber
        ? $requestedPhoneNumber
        : clean_assignment_phone_number($_POST['exhibit_phone_number'] ?? null);
    $ttyPhoneNumber = clean_assignment_phone_number($_POST['tty_phone_number'] ?? null);
    $paperClassificationCode = clean_assignment_paper_classification_code($_POST['paper_classification_code'] ?? null);

    $statement = db()->prepare(
        "UPDATE audio_files
         SET
            exhibit_phone_number = ?,
            tty_phone_number = ?,
            paper_classification_code = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND is_deleted = 0"
    );

    $statement->execute([
        $exhibitPhoneNumber,
        $ttyPhoneNumber,
        $paperClassificationCode,
        $id,
    ]);

    $effectiveAssignedNumber = $exhibitPhoneNumber !== null && $exhibitPhoneNumber !== ''
        ? $exhibitPhoneNumber
        : clean_assignment_phone_number($row['exhibit_phone_number'] ?? null);

    if ($effectiveAssignedNumber !== null && $effectiveAssignedNumber !== '') {
        $sharedStatement = db()->prepare(
            "UPDATE audio_files
             SET
                paper_classification_code = ?,
                updated_at = CURRENT_TIMESTAMP
             WHERE exhibit_phone_number = ?
               AND is_deleted = 0"
        );
        $sharedStatement->execute([
            $paperClassificationCode,
            $effectiveAssignedNumber,
        ]);
    }

    $updatedRow = find_assignment_audio_file($id);

    echo json_encode([
        'success' => true,
        'audio_file' => assignment_audio_payload($updatedRow),
    ], JSON_THROW_ON_ERROR);
}

function find_assignment_audio_file(int $id): ?array
{
    $statement = db()->prepare(
        "SELECT
            af.*,
            dpc.label AS paper_classification_label,
            dpc.color_hex AS paper_classification_color,
            dpc.description AS paper_classification_description
         FROM audio_files af
         LEFT JOIN directory_paper_classifications dpc
           ON dpc.code = af.paper_classification_code
         WHERE af.id = ?
           AND af.is_deleted = 0
         LIMIT 1"
    );
    $statement->execute([$id]);
    $row = $statement->fetch();

    return $row ?: null;
}

function assignment_audio_payload(array $row): array
{
    $ttyText = trim((string)($row['tty_transcription_text'] ?? ''));
    $hasTtyContent =
        $ttyText !== ''
        || trim((string)($row['tty_relative_path'] ?? '')) !== ''
        || (string)($row['tty_status'] ?? '') === 'complete';

    return [
        'id' => (int)$row['id'],
        'title' => assignment_audio_title($row),
        'requested_phone_number' => (string)($row['requested_phone_number'] ?? ''),
        'exhibit_phone_number' => (string)($row['exhibit_phone_number'] ?? ''),
        'tty_phone_number' => (string)($row['tty_phone_number'] ?? ''),
        'paper_classification_code' => (string)($row['paper_classification_code'] ?? ''),
        'paper_classification_label' => (string)($row['paper_classification_label'] ?? ''),
        'paper_classification_color' => (string)($row['paper_classification_color'] ?? ''),
        'paper_classification_description' => (string)($row['paper_classification_description'] ?? ''),
        'has_tty_content' => $hasTtyContent,
        'tty_transcription_text' => $ttyText,
    ];
}

function assignment_audio_title(array $row): string
{
    foreach (['directory_title', 'short_name', 'original_filename'] as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return 'Untitled audio file';
}

function paper_classification_assignment_options(): array
{
    $statement = db()->query(
        'SELECT code, label, description, color_hex
         FROM directory_paper_classifications
         WHERE is_active = 1
         ORDER BY sort_order ASC, code ASC'
    );

    $options = [[
        'code' => '',
        'label' => 'Unclassified',
        'description' => 'No page color assigned.',
        'color_hex' => '',
    ]];

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

function clean_assignment_phone_number(mixed $value): ?string
{
    $digits = preg_replace('/\D+/', '', (string)$value) ?? '';

    if ($digits === '') {
        return null;
    }

    return substr($digits, 0, 20);
}

function clean_assignment_paper_classification_code(mixed $value): ?string
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

    return $statement->fetchColumn() ? $code : null;
}
