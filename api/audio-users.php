<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

require_login();
require_current_terms_acceptance();

header('Content-Type: application/json; charset=utf-8');

$currentUser = current_user();

if ($currentUser === null) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Login required.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$userCounts = [];
$countStmt = db()->query('
    SELECT user_id, COUNT(*) AS file_count
    FROM audio_files
    WHERE is_deleted = 0
    GROUP BY user_id
');

foreach ($countStmt->fetchAll() as $row) {
    $userCounts[(int)$row['user_id']] = (int)$row['file_count'];
}

$allUsersCount = array_sum($userCounts);

$users = [
    [
        'id' => 'all',
        'username' => 'All Users',
        'file_count' => $allUsersCount,
    ],
];

$currentUserFileCount = $userCounts[(int)$currentUser['id']] ?? 0;

if ($currentUserFileCount > 0) {
    $users[] = [
        'id' => (string)$currentUser['id'],
        'username' => (string)$currentUser['username'],
        'file_count' => $currentUserFileCount,
    ];
}

if (is_admin()) {
    $stmt = db()->prepare('
        SELECT id, username
        FROM users
        WHERE is_active = 1
          AND id <> ?
        ORDER BY username ASC, id ASC
    ');
    $stmt->execute([$currentUser['id']]);

    foreach ($stmt->fetchAll() as $row) {
        $userId = (int)$row['id'];
        $fileCount = $userCounts[$userId] ?? 0;

        if ($fileCount <= 0) {
            continue;
        }

        $users[] = [
            'id' => (string)$row['id'],
            'username' => (string)$row['username'],
            'file_count' => $fileCount,
        ];
    }
}

echo json_encode([
    'success' => true,
    'is_admin' => is_admin(),
    'current_user_id' => (int)$currentUser['id'],
    'users' => $users,
], JSON_THROW_ON_ERROR);
