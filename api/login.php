<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (current_user() !== null) {
    $user = current_user();
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user' => [
            'id' => (int)$user['id'],
            'username' => (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? 'user'),
        ],
        'redirect_url' => '/dashboard.php',
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid security token.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$identity = trim((string)($_POST['identity'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($identity === '' || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Username/email and password are required.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$user = find_user_by_username_or_email($identity);

if (!$user || !(bool)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid login credentials.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
    $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash_for_storage($password), $user['id']]);
}

$terms = current_terms();
login_user((int)$user['id'], (string)$user['role']);

echo json_encode([
    'success' => true,
    'logged_in' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => (string)($user['username'] ?? ''),
        'email' => (string)($user['email'] ?? ''),
        'role' => (string)($user['role'] ?? 'user'),
    ],
    'redirect_url' => $user['agreed_terms_version'] !== $terms['version']
        ? '/accept-terms.php'
        : '/dashboard.php',
], JSON_THROW_ON_ERROR);
