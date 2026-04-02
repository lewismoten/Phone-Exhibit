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
        OR transcription_text LIKE ?
    )';
    $like = '%' . $q . '%';
    $params[] = $like;
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

function status_badge(string $status, string $type = 'default'): string
{
    $status = strtolower(trim($status));
    $label = ucfirst($type === 'default' ? $status : $type);

    return '<span class="status-badge status-'.$status.'" title="'.e($status).
     '">' . e($label) . '</span>';
}

function transcript_preview(?string $text, int $length = 180): string
{
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length - 1) . '…';
}

html_header('My Audio Files');
?>
<h1>My audio files</h1>
<?php if ($success): ?><div class="success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<p><a href="upload-audio.php">Upload another file</a></p>

<form method="get" style="margin-bottom:16px;">
    <label for="q">Search</label>
    <input id="q" name="q" value="<?= e($q) ?>" placeholder="Search filename, format, or transcript">
    <button type="submit">Search</button>
</form>

<?php if (!$rows): ?>
    <p>No audio files found.</p>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($rows as $row): ?>
            <?php
            $hasOriginal = !empty($row['relative_path']);
            $originalDuration = $row['duration_seconds'] !== null ? format_duration((float)$row['duration_seconds']) : 'Unknown';
            $originalSize = $row['file_size_bytes'] ? human_file_size((int)$row['file_size_bytes']) : 'Unknown';
            $originalUrl = $hasOriginal ? original_audio_playback_url($row) : null;

            $conversionStatus = (string)($row['conversion_status'] ?? 'pending');
            $hasConverted = !empty($row['converted_relative_path']) && $conversionStatus === 'complete';
            $convertedDuration = $row['converted_duration_seconds'] !== null ? format_duration((float)$row['converted_duration_seconds']) : null;
            $convertedSize = $row['converted_file_size_bytes'] ? human_file_size((int)$row['converted_file_size_bytes']) : null;
            $convertedUrl = $hasConverted ? converted_audio_playback_url($row) : null;

            $transcriptionText = trim((string)($row['transcription_text'] ?? ''));
            $transcriptionPreview = transcript_preview($transcriptionText);
            $transcriptionStatus = (string)($row['transcription_status'] ?? 'pending');

            $ttyStatus = (string)($row['tty_status'] ?? 'pending');
            $hasTty = !empty($row['tty_relative_path']) && $ttyStatus === 'complete';
            $ttySize = !empty($row['tty_file_size_bytes']) ? human_file_size((int)$row['tty_file_size_bytes']) : null;
            $ttyDuration = $row['tty_duration_seconds'] !== null ? format_duration((float)$row['tty_duration_seconds']) : null;
            $ttyUrl = $hasTty ? tty_audio_playback_url($row) : null;

            ?>
            <article style="
                border:1px solid #ddd;
                border-radius:12px;
                padding:12px;
                background:#fff;
            ">
                <div style="
                    display:flex;
                    justify-content:space-between;
                    align-items:flex-start;
                    gap:12px;
                    flex-wrap:wrap;
                ">
                    <div style="min-width:0;flex:1 1 500px;">
                        <div style="
                            display:flex;
                            align-items:center;
                            gap:8px;
                            flex-wrap:wrap;
                            margin-bottom:6px;
                        ">
                            <strong style="
                                font-size:16px;
                                line-height:1.2;
                                word-break:break-word;
                            "><?= e((string)$row['original_filename']) ?></strong>

                            
                            
                        </div>
                    </div>

                    <form method="post" action="delete-audio.php" onsubmit="return confirm('Delete this file?');" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" title="Delete this file">🗑 Delete</button>
                    </form>
                </div>

                <div style="
                    display:grid;
                    grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));
                    gap:10px;
                    margin-top:4px;
                ">
                    <div style="border:1px solid #eee;border-radius:10px;padding:10px;background:#fafafa;">
                        <div style="font-size:13px;font-weight:600;margin-bottom:6px;">🎵 Original</div>
                        <div> 
                              <span title="Original Duration">⏱ <?= e($originalDuration) ?></span>
                              <span title="Original File Size">💾 <?= e($originalSize) ?></span>
                              <span title="Original Format">🎚 <?= e((string)($row['audio_format'] ?: strtoupper((string)$row['file_ext']))) ?></span>
                        </div>
                        <?php if ($originalUrl): ?>
                            <audio controls preload="none" style="width:100%;">
                                <source src="<?= e($originalUrl) ?>" type="<?= e((string)$row['mime_type']) ?>">
                                Your browser does not support audio playback.
                            </audio>
                        <?php else: ?>
                            <div style="font-size:13px;color:#666;">Original file unavailable.</div>
                        <?php endif; ?>
                    </div>

                    <div style="border:1px solid #eee;border-radius:10px;padding:10px;background:#fafafa;">
                        <div style="font-size:13px;font-weight:600;margin-bottom:6px;">☎️ Converted</div>
                        <div>
                          <?= status_badge($conversionStatus) ?>
                          <span title="Converted Duration">⏱ <?= e($convertedDuration ?? '—') ?></span>
                          <span title="Converted File Size">💾 <?= $convertedSize ? e($convertedSize) : '—' ?></span>
                          <?php if (!empty($row['converted_audio_format'])): ?>
                          <span title="Converted Format">
                            ➡️ <?= e((string)$row['converted_audio_format']) ?>
                          </span>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($convertedUrl)): ?>
                            <audio controls preload="none" style="width:100%;">
                                <source src="<?= e($convertedUrl) ?>" type="<?= e((string)($row['converted_mime_type'] ?: 'audio/wav')) ?>">
                                Your browser does not support audio playback.
                            </audio>
                        <?php elseif ($conversionStatus === 'failed'): ?>
                            <div style="font-size:13px;color:#b42318;">Conversion failed.</div>
                        <?php else: ?>
                            <div style="font-size:13px;color:#666;">Not converted file yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="border:1px solid #eee;border-radius:10px;padding:10px;background:#fafafa;">
                  <div style="font-size:13px;font-weight:600;margin-bottom:6px;">📟 TTY Audio</div>
                  <div>
                    <?= status_badge($ttyStatus) ?>
                    <span title="TTY Duration">⏱ <?= e($ttyDuration ?? '—') ?></span>
                    <span title="TTY File Size">💾 <?= $ttySize ? e($ttySize) : '—' ?></span>
                    <span title="TTY Format">🎚 WAV</span>
                  </div>

                  <?php if (!empty($ttyUrl)): ?>
                      <audio controls preload="none" style="width:100%;">
                          <source src="<?= e($ttyUrl) ?>" type="audio/wav">
                          Your browser does not support audio playback.
                      </audio>
                  <?php elseif ($ttyStatus === 'failed'): ?>
                      <div style="font-size:13px;color:#b42318;">TTY conversion failed.</div>
                  <?php elseif ($ttyStatus === 'skipped'): ?>
                      <div style="font-size:13px;color:#666;">TTY audio was skipped.</div>
                  <?php else: ?>
                      <div style="font-size:13px;color:#666;">TTY audio not available yet.</div>
                  <?php endif; ?>
              </div>

                <?php if (!empty($row['conversion_error'])): ?>
                    <div style="margin-top:8px;font-size:13px;color:#b42318;">
                        <strong>Conversion error:</strong> <?= e((string)$row['conversion_error']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($row['transcription_error'])): ?>
                    <div style="margin-top:8px;font-size:13px;color:#b42318;">
                        <strong>Transcription error:</strong> <?= e((string)$row['transcription_error']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($row['tty_error'])): ?>
                    <div style="margin-top:8px;font-size:13px;color:#b42318;">
                        <strong>TTY error:</strong> <?= e((string)$row['tty_error']) ?>
                    </div>
                <?php endif; ?>

                <details style="margin-top:10px;">
                    <summary style="cursor:pointer;font-weight:600;">
                        📝 Transcription: <?= status_badge($transcriptionStatus) ?>
                        <?php if ($transcriptionPreview !== ''): ?>
                            <span style="font-weight:400;color:#555;">— <?= e($transcriptionPreview) ?></span>
                        <?php endif; ?>
                    </summary>

                    <div style="
                        margin-top:8px;
                        padding:10px;
                        border:1px solid #eee;
                        border-radius:10px;
                        background:#fcfcfc;
                        font-size:14px;
                        line-height:1.45;
                        white-space:pre-wrap;
                    "><?= $transcriptionText !== '' ? e($transcriptionText) : 'No transcription available yet.' ?></div>
                </details>
            </article>
        <?php endforeach; ?>
    </div>

    <nav style="margin-top:16px;">
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