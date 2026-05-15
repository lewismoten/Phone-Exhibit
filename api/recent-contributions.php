<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = db()->prepare(
        "SELECT
            id,
            coalesce(short_name, original_filename) AS title,
            created_at AS date
         FROM audio_files
         WHERE is_deleted = 0
         ORDER BY created_at DESC, id DESC
         LIMIT 5"
    );

    $stmt->execute([]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'entries' => $rows,
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Unable to load recent audio entries.',
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}