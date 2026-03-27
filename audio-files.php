<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
require_login();
require_current_terms_acceptance();

$user = current_user();
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = pagination_offset($page, $perPage);

$where = 'WHERE user_id = ? AND is_deleted = 0';
$params = [$user['id']];

if ($q !== '') {
    $where .= ' AND (
        original_filename LIKE ?
        OR audio_format LIKE ?
        OR audio_type LIKE ?
        OR converted_audio_format LIKE ?
        OR converted_audio_type LIKE ?
    )';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
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
            <?php
            $displaySize = $row['converted_file_size_bytes'] ?? $row['file_size_bytes'];
            $displayDuration = $row['converted_duration_seconds'] ?? $row['duration_seconds'];
            $displayFormat = $row['converted_audio_format'] ?: $row['audio_format'] ?: strtoupper((string)$row['file_ext']);
            $displayAudioType = $row['converted_audio_type'] ?: $row['audio_type'];
            $displayChannelMode = $row['converted_channel_mode'] ?: $row['channel_mode'] ?: 'Unknown';
            $displaySampleRate = $row['converted_sample_rate_hz'] ?? $row['sample_rate_hz'];
            ?>
            <p>
                <strong>Size:</strong> <?= e(human_file_size((int)$displaySize)) ?><br>
                <strong>Duration:</strong> <?= e(format_duration($displayDuration !== null ? (float)$displayDuration : null)) ?><br>
                <strong>Format:</strong> <?= e((string)$displayFormat) ?><br>
                <strong>Audio type:</strong> <?= e((string)$displayAudioType) ?><br>
                <strong>Channels:</strong> <?= e((string)$displayChannelMode) ?><br>
                <strong>Sample rate:</strong> <?= $displaySampleRate ? e(number_format((int)$displaySampleRate) . ' Hz') : 'Unknown' ?><br>
                <strong>Conversion:</strong> <?= e((string)$row['conversion_status']) ?>
            </p>
            <?php if (!empty($row['conversion_error'])): ?>
              <div class="error"><?= e((string)$row['conversion_error']) ?></div>
            <?php endif; ?>

            <audio controls preload="none" style="width:100%;max-width:480px;">
                <source src="<?= e(audio_playback_url($row)) ?>" type="<?= e((string)($row['converted_mime_type'] ?: $row['mime_type'])) ?>">
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