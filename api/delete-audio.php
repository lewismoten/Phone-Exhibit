<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

try {
    delete_audio_file($user);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Server error while deleting audio file.',
    ], JSON_THROW_ON_ERROR);
}

function delete_audio_file(array $user): void
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

    $stmt = db()->prepare(
        "SELECT *
         FROM audio_files
         WHERE id = ?
           AND is_deleted = 0
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Audio file not found.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $isOwner = (int)($row['user_id'] ?? 0) === (int)$user['id'];
    if (!$isOwner && !is_admin()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'You do not have permission to delete this audio file.',
        ], JSON_THROW_ON_ERROR);
        return;
    }

    $fullPath = rtrim(UPLOAD_BASE_DIR, '/\\') . DIRECTORY_SEPARATOR . (string)$row['relative_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    if (!empty($row['converted_relative_path'])) {
        $convertedFullPath = rtrim(UPLOAD_BASE_DIR, '/\\') . DIRECTORY_SEPARATOR . (string)$row['converted_relative_path'];
        if (is_file($convertedFullPath)) {
            @unlink($convertedFullPath);
        }
    }

    $update = db()->prepare(
        "UPDATE audio_files
         SET is_deleted = 1, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?
           AND is_deleted = 0"
    );
    $update->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Audio file deleted.',
        'redirect_url' => '/dashboard.php?audio_deleted=1',
    ], JSON_THROW_ON_ERROR);
}
