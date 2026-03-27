<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my-numbers.php');
    exit;
}

verify_csrf();

$profileId = (int)($_POST['id'] ?? 0);

if ($profileId <= 0) {
    flash_set('error', 'Invalid custom number request.');
    header('Location: my-numbers.php');
    exit;
}

$stmt = db()->prepare("
    SELECT id, number_assignment_status, telephone_number_id, custom_requested_number
    FROM number_profiles
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");
$stmt->execute([$profileId, $user['id']]);
$profile = $stmt->fetch();

if (!$profile) {
    flash_set('error', 'Number profile not found.');
    header('Location: my-numbers.php');
    exit;
}

if (($profile['number_assignment_status'] ?? '') !== 'requested') {
    flash_set('error', 'Only pending custom number requests can be cancelled.');
    header('Location: my-numbers.php');
    exit;
}

try {
    $stmt = db()->prepare("
        UPDATE number_profiles
        SET
            status = 'cancelled',
            is_soft_deleted = 1,
            soft_deleted_at = NOW(),
            soft_delete_reason = 'Custom number request cancelled by user'
        WHERE id = ?
          AND user_id = ?
    ");
    $stmt->execute([$profileId, $user['id']]);

    flash_set('success', 'Custom number request cancelled.');
} catch (Throwable $e) {
    flash_set('error', 'Unable to cancel the custom number request.');
}

header('Location: my-numbers.php');
exit;