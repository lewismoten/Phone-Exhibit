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

if (!hash_equals(csrf_token(), (string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid security token.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Please enter a valid email address.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+' . PASSWORD_RESET_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $clearOld = db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
    $clearOld->execute([$user['id']]);

    $insert = db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $insert->execute([$user['id'], $tokenHash, $expiresAt]);

    $resetUrl = BASE_URL . '/reset-password.php?token=' . urlencode($token);
    send_password_reset_email($user['email'], $resetUrl);
}

echo json_encode([
    'success' => true,
    'message' => 'If that email exists in our system, a reset link has been sent.',
], JSON_THROW_ON_ERROR);
