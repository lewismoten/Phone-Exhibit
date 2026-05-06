<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_login();
require_admin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $phoneNumbers = $_POST['phone_number'] ?? [];
        $shortNames = $_POST['short_name'] ?? [];

        if (!is_array($phoneNumbers)) {
            throw new RuntimeException('Invalid phone number data.');
        }

        $stmt = db()->prepare("
            UPDATE audio_files
            SET exhibit_phone_number = ?,
                short_name = ?,
                updated_at = NOW()
            WHERE id = ?
              AND is_deleted = 0
        ");

        foreach ($phoneNumbers as $id => $phoneNumber) {
            $id = (int)$id;
            $phoneNumber = trim((string)$phoneNumber);
            $shortName = trim((string)($shortNames[$id] ?? ''));

            if ($phoneNumber !== '' && !preg_match('/^[0-9*#\- ]{1,20}$/', $phoneNumber)) {
                throw new RuntimeException("Invalid phone number for audio ID {$id}.");
            }
            if (mb_strlen($shortName) > 120) {
                throw new RuntimeException("Short name too long for audio ID {$id}.");
            }

            $stmt->execute([
                $phoneNumber !== '' ? $phoneNumber : null,
                $shortName !== '' ? $shortName : null,
                $id,
            ]);
        }

        $success = 'Phone numbers saved.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = db()->query("
    SELECT
        af.id,
        af.user_id,
        af.original_filename,
        af.short_name,
        af.exhibit_phone_number,
        af.converted_relative_path,
        af.relative_path,
        af.transcription_text,
        af.conversion_status,
        af.created_at,
        u.username
    FROM audio_files af
    INNER JOIN users u
        ON u.id = af.user_id
    WHERE af.is_deleted = 0
    ORDER BY
      af.exhibit_phone_number IS NULL,
      CAST(af.exhibit_phone_number AS UNSIGNED) ASC,
      af.exhibit_phone_number ASC,
      af.short_name ASC,
      af.original_filename ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

html_header('Admin Audio Phone List');
?>

<h1>Admin Audio Phone List</h1>

<?php if ($success): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Name</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Phone #</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Preview</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Transcript</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $id = (int)$row['id'];

                $name = trim((string)($row['short_name'] ?? ''));
                if ($name === '') {
                    $name = (string)$row['original_filename'];
                }

                $previewText = trim((string)($row['transcription_text'] ?? ''));
                if (mb_strlen($previewText) > 80) {
                    $previewText = mb_substr($previewText, 0, 79) . '…';
                }

                $audioUrl = null;

                if (!empty($row['converted_relative_path']) && ($row['conversion_status'] ?? '') === 'complete') {
                    $audioUrl = upload_file_url((string)$row['converted_relative_path']);
                } elseif (!empty($row['relative_path'])) {
                    $audioUrl = upload_file_url((string)$row['relative_path']);
                }
                ?>

                <tr>
                    <td style="border-bottom:1px solid #eee;padding:6px;vertical-align:middle;">
                        <input
                            type="text"
                            name="short_name[<?= $id ?>]"
                            value="<?= e((string)($row['short_name'] ?: $row['original_filename'])) ?>"
                            maxlength="120"
                            style="width:100%;max-width:260px;"
                        >

                        <div style="font-size:12px;color:#666;margin-top:3px;">
                            @<?= e((string)$row['username']) ?> · Audio ID <?= $id ?>
                        </div>
                    </td>

                    <td style="border-bottom:1px solid #eee;padding:6px;vertical-align:middle;width:130px;">
                        <input
                            type="text"
                            name="phone_number[<?= $id ?>]"
                            value="<?= e((string)($row['exhibit_phone_number'] ?? '')) ?>"
                            placeholder="101"
                            style="width:100px;"
                        >
                    </td>

                    <td style="border-bottom:1px solid #eee;padding:6px;vertical-align:middle;width:260px;">
                        <?php if ($audioUrl): ?>
                            <audio controls preload="none" style="width:240px;">
                                <source src="<?= e($audioUrl) ?>" type="audio/wav">
                            </audio>
                        <?php else: ?>
                            <small>No audio preview</small>
                        <?php endif; ?>
                    </td>

                    <td style="border-bottom:1px solid #eee;padding:6px;vertical-align:middle;">
                        <small><?= e($previewText ?: 'No transcript') ?></small>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:16px;">
        <button type="submit">Save phone numbers</button>
    </p>
</form>

<?php html_footer(); ?>