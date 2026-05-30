<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function format_exhibit_number(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';

    if (strlen($digits) === 7) {
        return substr($digits, 0, 3) . '-' . substr($digits, 3);
    }

    return $number;
}

$stmt = db()->query("
    SELECT DISTINCT
        COALESCE(NULLIF(short_name, ''), original_filename) AS display_name,
        exhibit_phone_number,
        tty_phone_number
    FROM audio_files
    WHERE is_deleted = 0
      AND (
            (exhibit_phone_number IS NOT NULL AND exhibit_phone_number <> '')
         OR (tty_phone_number IS NOT NULL AND tty_phone_number <> '')
      )
    ORDER BY
        display_name ASC,
        CAST(exhibit_phone_number AS UNSIGNED) ASC,
        exhibit_phone_number ASC,
        CAST(tty_phone_number AS UNSIGNED) ASC,
        tty_phone_number ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

html_header('Phone Directory');
?>

<h1>Phone Directory</h1>

<?php if (!$rows): ?>
    <p>No phone numbers are currently listed.</p>
<?php else: ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Name</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Audio</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">TTY</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td style="border-bottom:1px solid #eee;padding:6px;">
                        <?= e((string)$row['display_name']) ?>
                    </td>

                    <td style="border-bottom:1px solid #eee;padding:6px;">
                        <?php if (!empty($row['exhibit_phone_number'])): ?>
                            <?= e(format_exhibit_number((string)$row['exhibit_phone_number'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td style="border-bottom:1px solid #eee;padding:6px;">
                        <?php if (!empty($row['tty_phone_number'])): ?>
                            <?= e(format_exhibit_number((string)$row['tty_phone_number'])) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php html_footer(); ?>