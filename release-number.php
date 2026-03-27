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

$stmt = db()->prepare("
    SELECT np.id, np.telephone_number_id
    FROM number_profiles np
    WHERE np.id = ?
      AND np.user_id = ?
    LIMIT 1
");
$stmt->execute([$profileId, $user['id']]);
$profile = $stmt->fetch();

if (!$profile || empty($profile['telephone_number_id'])) {
    flash_set('error', 'Number profile not found.');
    header('Location: my-numbers.php');
    exit;
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare("
        UPDATE telephone_numbers
        SET
            release_status = 'pending_release',
            release_requested_at = NOW(),
            release_message_mode = 'out_of_order'
        WHERE id = ?
          AND reserved_by_user_id = ?
    ");
    $stmt->execute([
        $profile['telephone_number_id'],
        $user['id'],
    ]);

    $stmt = db()->prepare("
        UPDATE number_profiles
        SET
            is_soft_deleted = 1,
            soft_deleted_at = NOW(),
            soft_delete_reason = 'User requested release',
            status = 'pending_release'
        WHERE id = ?
          AND user_id = ?
    ");
    $stmt->execute([$profileId, $user['id']]);

    db()->commit();

    flash_set('success', 'The number has been marked for release and will play an out-of-order message until fully released.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    flash_set('error', 'Unable to release the number.');
}

header('Location: my-numbers.php');
exit;