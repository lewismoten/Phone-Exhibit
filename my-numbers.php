<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
$success = flash_get('success') ?? '';
$error = flash_get('error') ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$q = trim((string)($_GET['q'] ?? ''));

$where = "WHERE np.user_id = ?";
$params = [$user['id']];

if ($q !== '') {
    $where .= " AND (
        tn.display_number LIKE ?
        OR np.custom_requested_number LIKE ?
        OR np.listing_text LIKE ?
    )";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countSql = "
    SELECT COUNT(*)
    FROM number_profiles np
    LEFT JOIN telephone_numbers tn ON tn.id = np.telephone_number_id
    $where
";

$stmt = db()->prepare($countSql);
$stmt->execute($params);
$totalRows = (int)$stmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "
    SELECT
        np.id,
        np.listing_text,
        np.number_assignment_status,
        np.status,
        np.publish_physical_directory,
        np.publish_web_directory,
        np.custom_requested_number,
        np.created_at,
        np.updated_at,
        tn.display_number,
        nt.label AS number_type_label,
        pm.label AS playback_mode_label
    FROM number_profiles np
    LEFT JOIN telephone_numbers tn ON tn.id = np.telephone_number_id
    LEFT JOIN number_types nt ON nt.id = np.number_type_id
    LEFT JOIN playback_modes pm ON pm.id = np.playback_mode_id
    $where
    ORDER BY np.updated_at DESC, np.id DESC
    LIMIT ? OFFSET ?
";

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$stmt = db()->prepare($listSql);
$stmt->execute($listParams);
$rows = $stmt->fetchAll();

html_header('My Numbers');
?>

<h1>My numbers</h1>

<?php if ($success): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<p>
    <a href="reserve-number.php">Reserve or request a number</a>
</p>

<form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
    <div style="flex:1;min-width:240px;">
        <label for="q">Search</label>
        <input
            id="q"
            name="q"
            value="<?= e($q) ?>"
            placeholder="Number or listing text"
        >
    </div>
    <div>
        <button type="submit">Search</button>
    </div>
    <div>
        <a href="my-numbers.php" style="display:inline-block;padding:10px 14px;border:1px solid #ccc;border-radius:8px;text-decoration:none;">Reset</a>
    </div>
</form>

<p style="margin-top:20px;">
    Showing <?= count($rows) ?> of <?= $totalRows ?> number profiles.
</p>

<?php if (!$rows): ?>
    <p>You have not reserved or requested any numbers yet.</p>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;margin-top:12px;">
            <thead>
                <tr>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Number</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Listing Text</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Assignment</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Profile</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Type</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Playback</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">In Directory</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $numberLabel = $row['display_number'] ?: $row['custom_requested_number'] ?: 'Unassigned';
                    $directoryLabelParts = [];

                    if ((int)$row['publish_physical_directory'] === 1) {
                        $directoryLabelParts[] = 'Physical';
                    }

                    if ((int)$row['publish_web_directory'] === 1) {
                        $directoryLabelParts[] = 'Web';
                    }

                    $directoryLabel = $directoryLabelParts ? implode(', ', $directoryLabelParts) : 'No';
                    ?>
                    <tr>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)$numberLabel) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)($row['listing_text'] ?? '')) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)($row['number_assignment_status'] ?? '')) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)($row['status'] ?? '')) ?>
                        </td>
                        <?php if (($row['status'] ?? '') === 'pending_release'): ?>
                          <td colspan="3" style="font-size:12px;color:#666;">
                            Calls will hear an out-of-order message until fully released.
                          </td>
                        <?php else: ?>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)($row['number_type_label'] ?? 'Not set')) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)($row['playback_mode_label'] ?? 'Not set')) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e($directoryLabel) ?>
                        </td>
                        <?php endif; ?>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                          <a href="edit-number-profile.php?id=<?= (int)$row['id'] ?>">Edit</a>

                          <?php if (($row['number_assignment_status'] ?? '') === 'requested'): ?>
                              <form method="post" action="cancel-custom-number-request.php" class="inline-form">
                                  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                  <button type="submit" onclick="return confirm('Cancel this custom number request?')">Cancel</button>
                              </form>

                          <?php elseif (($row['number_assignment_status'] ?? '') === 'assigned'): ?>
                              <?php if (($row['status'] ?? '') === 'pending_release'): ?>
                                  <form method="post" action="recover-number.php" class="inline-form">
                                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                      <button type="submit">Reclaim</button>
                                  </form>
                              <?php else: ?>
                                  <form method="post" action="release-number.php" class="inline-form">
                                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                      <button type="submit" onclick="return confirm('Release this number? It will temporarily play an out-of-order message until fully released.')">Release</button>
                                  </form>
                              <?php endif; ?>
                          <?php endif; ?>
                      </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php $baseQuery = ['q' => $q]; ?>

    <nav style="margin-top:16px;">
        <?php if ($page > 1): ?>
            <a href="?<?= e(http_build_query($baseQuery + ['page' => $page - 1])) ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span style="margin:0 12px;">Page <?= $page ?> of <?= $totalPages ?></span>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= e(http_build_query($baseQuery + ['page' => $page + 1])) ?>">Next &raquo;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php html_footer(); ?>