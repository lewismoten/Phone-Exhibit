<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();

if (!$user) {
    echo json_encode([
        'success' => true,
        'logged_in' => false,
        'user' => null,
    ], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => (string)($user['username'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'role' => (string)($user['role'] ?? 'user'),
    ],
], JSON_THROW_ON_ERROR);
