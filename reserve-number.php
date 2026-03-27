<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
$error = '';
$success = flash_get('success') ?? '';
$info = flash_get('info') ?? '';

function reserve_area_codes(): array
{
    $stmt = db()->query("
        SELECT DISTINCT area_code
        FROM telephone_numbers
        WHERE number_format = 'full'
          AND area_code IS NOT NULL
          AND area_code <> ''
        ORDER BY area_code
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function reserve_central_office_codes(): array
{
    $stmt = db()->query("
        SELECT DISTINCT central_office_code
        FROM telephone_numbers
        WHERE number_format = 'full'
          AND central_office_code IS NOT NULL
          AND central_office_code <> ''
        ORDER BY central_office_code
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function reserve_number_filters(array $input): array
{
    $where = [
        "tn.is_active = 1",
        "tn.is_reserved = 0",
    ];
    $params = [];

    $numberFormat = trim((string)($input['number_format'] ?? ''));
    $areaCode = trim((string)($input['area_code'] ?? ''));
    $centralOfficeCode = trim((string)($input['central_office_code'] ?? ''));
    $query = trim((string)($input['q'] ?? ''));

    if ($numberFormat !== '') {
        $where[] = "tn.number_format = ?";
        $params[] = $numberFormat;
    }

    if ($areaCode !== '') {
        $where[] = "tn.area_code = ?";
        $params[] = $areaCode;
    }

    if ($centralOfficeCode !== '') {
        $where[] = "tn.central_office_code = ?";
        $params[] = $centralOfficeCode;
    }

    if ($query !== '') {
        $where[] = "(tn.full_number LIKE ? OR tn.display_number LIKE ?)";
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    return [
        'where_sql' => implode(' AND ', $where),
        'params' => $params,
    ];
}

function reserve_count_numbers(array $input): int
{
    $filters = reserve_number_filters($input);

    $sql = "
        SELECT COUNT(*)
        FROM telephone_numbers tn
        WHERE {$filters['where_sql']}
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($filters['params']);

    return (int)$stmt->fetchColumn();
}

function reserve_search_numbers(array $input, int $limit, int $offset): array
{
    $filters = reserve_number_filters($input);

    $sql = "
        SELECT
            tn.id,
            tn.full_number,
            tn.display_number,
            tn.number_format
        FROM telephone_numbers tn
        WHERE {$filters['where_sql']}
        ORDER BY
            CASE tn.number_format
                WHEN 'short' THEN 1
                WHEN 'internal' THEN 2
                ELSE 3
            END,
            tn.display_number ASC
        LIMIT ? OFFSET ?
    ";

    $params = $filters['params'];
    $params[] = $limit;
    $params[] = $offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function reserve_random_number(array $input): ?array
{
    $filters = reserve_number_filters($input);

    $sql = "
        SELECT
            tn.id,
            tn.full_number,
            tn.display_number,
            tn.number_format
        FROM telephone_numbers tn
        WHERE {$filters['where_sql']}
        ORDER BY RAND()
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute($filters['params']);
    $row = $stmt->fetch();

    return $row ?: null;
}

function create_assigned_draft_number_profile(int $telephoneNumberId, int $userId, string $listingText): int
{
    $stmt = db()->prepare("
        INSERT INTO number_profiles (
            telephone_number_id,
            user_id,
            listing_text,
            number_assignment_status,
            status
        ) VALUES (?, ?, ?, 'assigned', 'draft')
    ");
    $stmt->execute([
        $telephoneNumberId,
        $userId,
        $listingText,
    ]);

    return (int)db()->lastInsertId();
}

function create_custom_requested_draft_number_profile(
    int $userId,
    string $requestedNumber,
    string $notes,
    string $listingText
): int {
    $stmt = db()->prepare("
        INSERT INTO number_profiles (
            telephone_number_id,
            user_id,
            custom_requested_number,
            custom_request_notes,
            listing_text,
            number_assignment_status,
            status
        ) VALUES (NULL, ?, ?, ?, ?, 'requested', 'draft')
    ");
    $stmt->execute([
        $userId,
        $requestedNumber,
        $notes,
        $listingText,
    ]);

    return (int)db()->lastInsertId();
}

$areaCodes = reserve_area_codes();
$centralOfficeCodes = reserve_central_office_codes();

$filters = [
    'number_format' => trim((string)($_REQUEST['number_format'] ?? '')),
    'area_code' => trim((string)($_REQUEST['area_code'] ?? '')),
    'central_office_code' => trim((string)($_REQUEST['central_office_code'] ?? '')),
    'q' => trim((string)($_REQUEST['q'] ?? '')),
];

$page = max(1, (int)($_REQUEST['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$customRequestedNumber = trim((string)($_POST['custom_requested_number'] ?? ''));
$customRequestNotes = trim((string)($_POST['custom_request_notes'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = trim((string)($_POST['action'] ?? ''));

    $filters = [
        'number_format' => trim((string)($_POST['number_format'] ?? $filters['number_format'])),
        'area_code' => trim((string)($_POST['area_code'] ?? $filters['area_code'])),
        'central_office_code' => trim((string)($_POST['central_office_code'] ?? $filters['central_office_code'])),
        'q' => trim((string)($_POST['q'] ?? $filters['q'])),
    ];

    if ($action === 'reserve') {
        $numberId = (int)($_POST['number_id'] ?? 0);

        if ($numberId <= 0) {
            $error = 'Please choose a number to reserve.';
        } else {
            try {
                db()->beginTransaction();

                $stmt = db()->prepare("
                    SELECT id, display_number, is_reserved
                    FROM telephone_numbers
                    WHERE id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$numberId]);
                $number = $stmt->fetch();

                if (!$number) {
                    throw new RuntimeException('That number no longer exists.');
                }

                if ((int)$number['is_reserved'] === 1) {
                    throw new RuntimeException('That number has already been reserved.');
                }

                $stmt = db()->prepare("
                    UPDATE telephone_numbers
                    SET
                        is_reserved = 1,
                        reserved_by_user_id = ?,
                        reserved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$user['id'], $numberId]);

                $profileId = create_assigned_draft_number_profile(
                    $numberId,
                    (int)$user['id'],
                    'Reserved ' . $number['display_number']
                );

                db()->commit();

                flash_set('success', 'Number reserved: ' . $number['display_number']);
                header('Location: edit-number-profile.php?id=' . urlencode((string)$profileId));
                exit;
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'reserve_random') {
        try {
            db()->beginTransaction();

            $number = reserve_random_number($filters);

            if (!$number) {
                throw new RuntimeException('No available numbers matched your filters.');
            }

            $stmt = db()->prepare("
                SELECT id, display_number, is_reserved
                FROM telephone_numbers
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([(int)$number['id']]);
            $lockedNumber = $stmt->fetch();

            if (!$lockedNumber) {
                throw new RuntimeException('The selected number no longer exists.');
            }

            if ((int)$lockedNumber['is_reserved'] === 1) {
                throw new RuntimeException('That number was just reserved by someone else. Please try again.');
            }

            $stmt = db()->prepare("
                UPDATE telephone_numbers
                SET
                    is_reserved = 1,
                    reserved_by_user_id = ?,
                    reserved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], (int)$number['id']]);

            $profileId = create_assigned_draft_number_profile(
                (int)$number['id'],
                (int)$user['id'],
                'Reserved ' . $lockedNumber['display_number']
            );

            db()->commit();

            flash_set('success', 'Number reserved: ' . $lockedNumber['display_number']);
            header('Location: edit-number-profile.php?id=' . urlencode((string)$profileId));
            exit;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $error = $e->getMessage();
        }
    } elseif ($action === 'request_custom') {
        if ($customRequestedNumber === '') {
            $error = 'Please enter the custom number you want.';
        } elseif (strlen($customRequestedNumber) > 32) {
            $error = 'The custom number is too long.';
        } else {
            try {
                $profileId = create_custom_requested_draft_number_profile(
                    (int)$user['id'],
                    $customRequestedNumber,
                    $customRequestNotes,
                    'Requested ' . $customRequestedNumber
                );

                flash_set('success', 'Custom number requested: ' . $customRequestedNumber);
                header('Location: edit-number-profile.php?id=' . urlencode((string)$profileId));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$totalRows = reserve_count_numbers($filters);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$rows = reserve_search_numbers($filters, $perPage, $offset);

html_header('Reserve a Number');
?>

<h1>Reserve a number</h1>
<p>Choose an available number, reserve any available number at random, or request a custom number for review.</p>

<?php if ($success): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($info): ?>
    <div class="success"><?= e($info) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0;">
    <form method="post" style="border:none;padding:0;margin:0;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="reserve_random">
        <input type="hidden" name="number_format" value="<?= e($filters['number_format']) ?>">
        <input type="hidden" name="area_code" value="<?= e($filters['area_code']) ?>">
        <input type="hidden" name="central_office_code" value="<?= e($filters['central_office_code']) ?>">
        <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
        <button type="submit">Reserve any available number</button>
    </form>
</div>

<form method="post" style="margin:16px 0;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="request_custom">

    <label for="custom_requested_number">Request a custom number</label>
    <input
        id="custom_requested_number"
        name="custom_requested_number"
        value="<?= e($customRequestedNumber) ?>"
        maxlength="32"
        placeholder="Examples: 1234, 555-2368, (540) 555-1234"
        required
    >

    <label for="custom_request_notes">Notes</label>
    <input
        id="custom_request_notes"
        name="custom_request_notes"
        value="<?= e($customRequestNotes) ?>"
        maxlength="255"
        placeholder="Why you want this number, or any context for review"
    >

    <button type="submit">Request and continue</button>

    <p style="font-size:0.9em;color:#555;margin-top:8px;">
        Custom numbers are reviewed before approval. Real-world numbers may require verification and may be restricted.
    </p>
</form>

<form method="get" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end;">
    <div>
        <label for="q">Search</label>
        <input
            id="q"
            name="q"
            value="<?= e($filters['q']) ?>"
            placeholder="911, 555, 1234, (540) 555..."
        >
    </div>

    <div>
        <label for="number_format">Format</label>
        <select id="number_format" name="number_format">
            <option value="" <?= $filters['number_format'] === '' ? 'selected' : '' ?>>Any</option>
            <option value="short" <?= $filters['number_format'] === 'short' ? 'selected' : '' ?>>Short</option>
            <option value="full" <?= $filters['number_format'] === 'full' ? 'selected' : '' ?>>Full</option>
            <option value="internal" <?= $filters['number_format'] === 'internal' ? 'selected' : '' ?>>Internal</option>
        </select>
    </div>

    <div>
        <label for="area_code">Area Code</label>
        <select id="area_code" name="area_code">
            <option value="">Any</option>
            <?php foreach ($areaCodes as $areaCode): ?>
                <option value="<?= e((string)$areaCode) ?>" <?= $filters['area_code'] === (string)$areaCode ? 'selected' : '' ?>>
                    <?= e((string)$areaCode) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="central_office_code">Exchange</label>
        <select id="central_office_code" name="central_office_code">
            <option value="">Any</option>
            <?php foreach ($centralOfficeCodes as $exchange): ?>
                <option value="<?= e((string)$exchange) ?>" <?= $filters['central_office_code'] === (string)$exchange ? 'selected' : '' ?>>
                    <?= e((string)$exchange) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="submit">Apply</button>
        <a href="reserve-number.php" style="display:inline-block;padding:10px 14px;border:1px solid #ccc;border-radius:8px;text-decoration:none;">Reset</a>
    </div>
</form>

<p style="margin-top:20px;">
    Showing <?= count($rows) ?> of <?= $totalRows ?> available numbers.
</p>

<?php if (!$rows): ?>
    <p>No available numbers matched your filters.</p>
<?php else: ?>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;margin-top:12px;">
            <thead>
                <tr>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Number</th>
                    <th style="text-align:left;border-bottom:1px solid #ddd;padding:8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <?= e((string)$row['display_number']) ?>
                        </td>
                        <td style="border-bottom:1px solid #eee;padding:8px;">
                            <form method="post" style="border:none;padding:0;margin:0;">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="reserve">
                                <input type="hidden" name="number_id" value="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="number_format" value="<?= e($filters['number_format']) ?>">
                                <input type="hidden" name="area_code" value="<?= e($filters['area_code']) ?>">
                                <input type="hidden" name="central_office_code" value="<?= e($filters['central_office_code']) ?>">
                                <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                                <button type="submit">Reserve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $baseQuery = [
        'number_format' => $filters['number_format'],
        'area_code' => $filters['area_code'],
        'central_office_code' => $filters['central_office_code'],
        'q' => $filters['q'],
    ];
    ?>

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