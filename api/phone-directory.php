<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $statement = db()->query(
        "SELECT
            af.id,
            af.short_name,
            af.directory_title,
            af.rolodex_title,
            af.original_filename,
            af.exhibit_phone_number,
            af.tty_phone_number,
            af.paper_classification_code,
            dpc.label AS paper_classification_label,
            dpc.color_hex AS paper_classification_color,
            dpc.description AS paper_classification_description,
            coalesce(dpc.sort_order, 9999) AS paper_classification_sort_order
         FROM audio_files af
         LEFT JOIN directory_paper_classifications dpc
           ON dpc.code = af.paper_classification_code
         WHERE af.is_deleted = 0
           AND af.exhibit_phone_number IS NOT NULL
           AND af.exhibit_phone_number <> ''
         ORDER BY
            coalesce(dpc.sort_order, 9999) ASC,
            dpc.code ASC,
            af.exhibit_phone_number ASC,
            af.short_name ASC,
            af.directory_title ASC,
            af.rolodex_title ASC,
            af.original_filename ASC,
            af.id ASC"
    );

    $entriesByPhone = [];

    foreach ($statement->fetchAll() as $row) {
        $phoneNumber = trim((string)($row['exhibit_phone_number'] ?? ''));
        if ($phoneNumber === '') {
            continue;
        }

        if (!isset($entriesByPhone[$phoneNumber])) {
            $entriesByPhone[$phoneNumber] = [
                'id' => (int)$row['id'],
                'title' => directory_entry_title($row),
                'phone_number' => $phoneNumber,
                'tty_phone_number' => trim((string)($row['tty_phone_number'] ?? '')),
                'audio_count' => 1,
                'paper_classification_code' => (string)($row['paper_classification_code'] ?? ''),
                'paper_classification_label' => (string)($row['paper_classification_label'] ?? ''),
                'paper_classification_color' => (string)($row['paper_classification_color'] ?? ''),
                'paper_classification_description' => (string)($row['paper_classification_description'] ?? ''),
                'paper_classification_sort_order' => (int)($row['paper_classification_sort_order'] ?? 9999),
            ];
            continue;
        }

        if ($entriesByPhone[$phoneNumber]['tty_phone_number'] === '') {
            $entriesByPhone[$phoneNumber]['tty_phone_number'] = trim((string)($row['tty_phone_number'] ?? ''));
        }

        $entriesByPhone[$phoneNumber]['audio_count']++;
    }

    $entries = array_values($entriesByPhone);

    echo json_encode([
        'success' => true,
        'entries' => $entries,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Unable to load phone directory.',
    ], JSON_THROW_ON_ERROR);
}

function directory_entry_title(array $row): string
{
    $shortName = trim((string)($row['short_name'] ?? ''));
    if ($shortName !== '') {
        return $shortName;
    }

    $directoryTitle = trim((string)($row['directory_title'] ?? ''));
    if ($directoryTitle !== '') {
        return $directoryTitle;
    }

    $rolodexTitle = trim((string)($row['rolodex_title'] ?? ''));
    if ($rolodexTitle !== '') {
        return $rolodexTitle;
    }

    $originalFilename = trim((string)($row['original_filename'] ?? ''));
    if ($originalFilename !== '') {
        return $originalFilename;
    }

    return 'Untitled listing';
}
