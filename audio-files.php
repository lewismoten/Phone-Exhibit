<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();

$user = current_user();
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = pagination_offset($page, $perPage);

$where = 'WHERE user_id = ? AND is_deleted = 0';
$params = [$user['id']];

if ($q !== '') {
    $where .= ' AND (original_filename LIKE ? OR audio_format LIKE ? OR audio_type LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM audio_files $where");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$listStmt = db()->prepare(
    "SELECT * FROM audio_files
     $where
     ORDER BY created_at DESC, id DESC
     LIMIT ? OFFSET ?"
);
$listStmt->execute($listParams);
$rows = $listStmt->fetchAll();

$success = flash_get('success') ?? '';
$error = flash_get('error') ?? '';

html_header('My Audio Files');
?>
<h1>My audio files</h1>
<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<p><a href="upload-audio.php">Upload another file</a></p>

<form method="get">
    <label for="q">Search</label>
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="Search filename, format, or MIME type">
    <button type="submit">Search</button>
</form>

<?php if (!$rows): ?>
    <p>No audio files found.</p>
<?php else: ?>
    <?php foreach ($rows as $row): ?>
        <article style="border:1px solid #ddd;border-radius:12px;padding:16px;margin:16px 0;">
            <h2 style="margin-top:0;"><?= e($row['original_filename']) ?></h2>
            <p>
                <strong>Size:</strong> <?= e(human_file_size((int)$row['file_size_bytes'])) ?><br>
                <strong>Duration:</strong> <?= e(format_duration($row['duration_seconds'] !== null ? (float)$row['duration_seconds'] : null)) ?><br>
                <strong>Format:</strong> <?= e((string)($row['audio_format'] ?: strtoupper((string)$row['file_ext']))) ?><br>
                <strong>Audio type:</strong> <?= e((string)$row['audio_type']) ?><br>
                <strong>Channels:</strong> <?= e((string)($row['channel_mode'] ?: 'Unknown')) ?><br>
                <strong>Sample rate:</strong> <?= $row['sample_rate_hz'] ? e(number_format((int)$row['sample_rate_hz']) . ' Hz') : 'Unknown' ?>
            </p>

            <audio controls preload="none" style="width:100%;max-width:480px;">
                <source src="<?= e(audio_public_url($row)) ?>" type="<?= e((string)$row['mime_type']) ?>">
                Your browser does not support audio playback.
            </audio>

            <form method="post" action="delete-audio.php" onsubmit="return confirm('Delete this file?');" style="margin-top:12px;border:none;padding:0;">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit">Delete</button>
            </form>
        </article>
    <?php endforeach; ?>

    <nav>
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(['q' => $q, 'page' => $page - 1]) ?>">&laquo; Previous</a>
        <?php endif; ?>

        <span> Page <?= $page ?> of <?= $totalPages ?> </span>

        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(['q' => $q, 'page' => $page + 1]) ?>">Next &raquo;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
<?php html_footer(); ?>