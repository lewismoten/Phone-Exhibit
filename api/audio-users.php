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

$users = [
    [
        'id' => 'all',
        'username' => 'All Users',
    ],
    [
        'id' => (string)$currentUser['id'],
        'username' => (string)$currentUser['username'],
    ],
];

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
        $users[] = [
            'id' => (string)$row['id'],
            'username' => (string)$row['username'],
        ];
    }
}

echo json_encode([
    'success' => true,
    'is_admin' => is_admin(),
    'current_user_id' => (int)$currentUser['id'],
    'users' => $users,
], JSON_THROW_ON_ERROR);
