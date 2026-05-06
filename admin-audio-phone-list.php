<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_login();
require_admin();

$success = '';
$error = '';

function get_exhibit_settings(): array
{
    $stmt = db()->query("SELECT setting_key, setting_value FROM exhibit_settings");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function save_exhibit_setting(string $key, string $value): void
{
    $stmt = db()->prepare("
        INSERT INTO exhibit_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");
    $stmt->execute([$key, $value]);
}
function download_phone_config(): void
{
    $stmt = db()->query("
        SELECT
            id,
            exhibit_phone_number,
            tty_phone_number,
            short_name
        FROM audio_files
        WHERE is_deleted = 0
          AND (
              (exhibit_phone_number IS NOT NULL AND exhibit_phone_number <> '')
              OR
              (tty_phone_number IS NOT NULL AND tty_phone_number <> '')
          )
        ORDER BY
            CAST(exhibit_phone_number AS UNSIGNED) ASC,
            exhibit_phone_number ASC,
            CAST(tty_phone_number AS UNSIGNED) ASC,
            tty_phone_number ASC,
            short_name ASC,
            id ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lines = [];
    $lines[] = '; Generated phone exhibit config';
    $lines[] = '; Generated at ' . date('Y-m-d H:i:s');
    $lines[] = '';
    $lines[] = '[phone-exhibit]';

    $regular = [];
    $tty = [];

    foreach ($rows as $row) {
        $id = (int)$row['id'];

        $phone = preg_replace('/[^0-9]/', '', (string)($row['exhibit_phone_number'] ?? ''));
        if ($phone !== '') {
            $regular[$phone][] = $row;
        }

        $ttyPhone = preg_replace('/[^0-9]/', '', (string)($row['tty_phone_number'] ?? ''));
        if ($ttyPhone !== '') {
            $tty[$ttyPhone][] = $row;
        }
    }

    append_random_playback_extensions($lines, $regular, 'phone-exhibit');
    append_random_playback_extensions($lines, $tty, 'phone-exhibit-tty', 'TTY ');
    append_admin_last($lines);

    $content = implode("\n", $lines) . "\n";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="phone-exhibit.conf"');
    header('Content-Length: ' . strlen($content));

    echo $content;
}
function append_admin_last(array &$lines): void
{
    $settings = get_exhibit_settings();

    // Pending/review recording portal
    $recordingExtension = preg_replace('/[^0-9]/', '', $settings['recording_extension'] ?? '7000');
    $directorPin = preg_replace('/[^0-9]/', '', $settings['director_pin'] ?? '123456');
    $pinDigits = (int)($settings['recording_pin_digits'] ?? 6);
    $targetDigits = (int)($settings['target_number_digits'] ?? 7);
    $minSilence = (int)($settings['recording_min_silence_seconds'] ?? 3);
    $maxSeconds = (int)($settings['recording_max_seconds'] ?? 300);
    $pendingDir = rtrim($settings['recordings_pending_dir'] ?? '/var/spool/asterisk/recordings/pending', '/');
    $enabled = ($settings['recording_enabled'] ?? '1') === '1';

    if ($enabled && $recordingExtension !== '' && $directorPin !== '') {
        $lines[] = '';
        $lines[] = '; Pending/review recording portal';
        $lines[] = 'exten => ' . $recordingExtension . ',1,Answer()';
        $lines[] = ' same => n,Read(PIN,,' . $pinDigits . ',,3,10)';
        $lines[] = ' same => n,GotoIf($["${PIN}" = "' . $directorPin . '"]?ok:badpin)';
        $lines[] = ' same => n(ok),Read(TARGET,,' . $targetDigits . ',,3,15)';
        $lines[] = ' same => n,GotoIf($["${REGEX("^[0-9]+$" ${TARGET})}" = "1"]?record:badnumber)';
        $lines[] = ' same => n(record),Set(TSTAMP=${STRFTIME(${EPOCH},,%Y%m%d-%H%M%S)})';
        $lines[] = ' same => n,Set(BASE=' . $pendingDir . '/${TARGET}-${TSTAMP}-${UNIQUEID})';
        $lines[] = ' same => n,Playback(beep)';
        $lines[] = ' same => n,Record(${BASE}.wav,' . $minSilence . ',' . $maxSeconds . ',k)';
        $lines[] = ' same => n,System(/usr/local/bin/write-recording-meta.sh "${BASE}" "${TARGET}" "${CALLERID(num)}" "${TSTAMP}")';
        $lines[] = ' same => n,Playback(auth-thankyou)';
        $lines[] = ' same => n,Hangup()';
        $lines[] = ' same => n(badpin),Playback(auth-incorrect)';
        $lines[] = ' same => n,Hangup()';
        $lines[] = ' same => n(badnumber),Playback(invalid)';
        $lines[] = ' same => n,Hangup()';
    }

    // Live/immediate overwrite recording portal
    $liveRecordingExtension = preg_replace('/[^0-9]/', '', $settings['live_recording_extension'] ?? '7100');
    $liveDirectorPin = preg_replace('/[^0-9]/', '', $settings['live_director_pin'] ?? '654321');
    $livePinDigits = (int)($settings['live_recording_pin_digits'] ?? 6);
    $liveTargetDigits = (int)($settings['live_target_number_digits'] ?? 7);
    $liveMinSilence = (int)($settings['live_recording_min_silence_seconds'] ?? 3);
    $liveMaxSeconds = (int)($settings['live_recording_max_seconds'] ?? 300);
    $liveDir = rtrim($settings['live_recordings_dir'] ?? '/var/lib/asterisk/sounds/phone-exhibit-live', '/');
    $liveEnabled = ($settings['live_recording_enabled'] ?? '1') === '1';

    if ($liveEnabled && $liveRecordingExtension !== '' && $liveDirectorPin !== '') {
        $lines[] = '';
        $lines[] = '; Live recording portal - immediately overwrites live number';
        $lines[] = 'exten => ' . $liveRecordingExtension . ',1,Answer()';
        $lines[] = ' same => n,Read(PIN,,' . $livePinDigits . ',,3,10)';
        $lines[] = ' same => n,GotoIf($["${PIN}" = "' . $liveDirectorPin . '"]?ok:badpin)';
        $lines[] = ' same => n(ok),Read(TARGET,,' . $liveTargetDigits . ',,3,15)';
        $lines[] = ' same => n,GotoIf($["${REGEX("^[0-9]+$" ${TARGET})}" = "1"]?record:badnumber)';
        $lines[] = ' same => n(record),Set(TMP=' . $liveDir . '/${TARGET}-tmp-${UNIQUEID})';
        $lines[] = ' same => n,Set(FINAL=' . $liveDir . '/${TARGET})';
        $lines[] = ' same => n,Playback(beep)';
        $lines[] = ' same => n,Record(${TMP}.wav,' . $liveMinSilence . ',' . $liveMaxSeconds . ',k)';
        $lines[] = ' same => n,System(/bin/mv "${TMP}.wav" "${FINAL}.wav")';
        $lines[] = ' same => n,System(/bin/chown asterisk:asterisk "${FINAL}.wav")';
        $lines[] = ' same => n,Playback(auth-thankyou)';
        $lines[] = ' same => n,Hangup()';
        $lines[] = ' same => n(badpin),Playback(auth-incorrect)';
        $lines[] = ' same => n,Hangup()';
        $lines[] = ' same => n(badnumber),Playback(invalid)';
        $lines[] = ' same => n,Hangup()';
    }

    // Fallback live playback lookup
    if ($liveEnabled) {
        $lines[] = '';
        $lines[] = '; Fallback live recording playback';
        $lines[] = '; If no explicit extension matched above, look for phone-exhibit-live/${EXTEN}.wav';
        $lines[] = 'exten => _X.,1,Answer()';
        $lines[] = ' same => n,Set(FILE=' . $liveDir . '/${EXTEN}.wav)';
        $lines[] = ' same => n,GotoIf($[${STAT(e,${FILE})}]?play:notfound)';
        $lines[] = ' same => n(play),Playback(phone-exhibit-live/${EXTEN})';
        $lines[] = ' same => n,Hangup()';
        $lines[] = ' same => n(notfound),Playback(invalid)';
        $lines[] = ' same => n,Hangup()';
    }
}
function append_random_playback_extensions(
    array &$lines,
    array $grouped,
    string $soundDir,
    string $commentPrefix = ''
): void {
    foreach ($grouped as $phone => $items) {
        $count = count($items);

        $lines[] = '';
        $lines[] = '; ' . $commentPrefix . 'Phone ' . $phone . ' has ' . $count . ' audio file(s)';
        $lines[] = 'exten => ' . $phone . ',1,Answer()';

        if ($count === 1) {
            $id = (int)$items[0]['id'];
            $wavName = $phone . '-' . $id;

            $lines[] = ' same => n,Playback(' . $soundDir . '/' . $wavName . ')';
            $lines[] = ' same => n,Hangup()';
            continue;
        }

        $lines[] = ' same => n,Set(PICK=${RAND(1,' . $count . ')})';
        $lines[] = ' same => n,Goto(${PICK})';

        foreach ($items as $index => $item) {
            $choice = $index + 1;
            $id = (int)$item['id'];
            $wavName = $phone . '-' . $id;

            $lines[] = ' same => n(' . $choice . '),Playback(' . $soundDir . '/' . $wavName . ')';
            $lines[] = ' same => n,Hangup()';
        }
    }
}
function download_wav_archive(): void
{
    $stmt = db()->query("
        SELECT
            id,
            exhibit_phone_number,
            tty_phone_number,
            converted_relative_path,
            tty_relative_path
        FROM audio_files
        WHERE is_deleted = 0
          AND (
              (exhibit_phone_number IS NOT NULL AND exhibit_phone_number <> '')
              OR
              (tty_phone_number IS NOT NULL AND tty_phone_number <> '')
          )
        ORDER BY id ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $zipPath = tempnam(sys_get_temp_dir(), 'phone_wavs_');
    if ($zipPath === false) {
        throw new RuntimeException('Unable to create temporary ZIP file.');
    }

    $zipFile = $zipPath . '.zip';
    rename($zipPath, $zipFile);

    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create ZIP archive.');
    }

    foreach ($rows as $row) {
        $id = (int)$row['id'];

        $phone = preg_replace('/[^0-9]/', '', (string)($row['exhibit_phone_number'] ?? ''));
        if ($phone !== '' && !empty($row['converted_relative_path'])) {
            $sourcePath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$row['converted_relative_path']);

            if (is_file($sourcePath)) {
                $zip->addFile($sourcePath, 'phone-exhibit/' . $phone . '-' . $id . '.wav');
            }
        }

        $ttyPhone = preg_replace('/[^0-9]/', '', (string)($row['tty_phone_number'] ?? ''));
        if ($ttyPhone !== '' && !empty($row['tty_relative_path'])) {
            $sourcePath = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$row['tty_relative_path']);

            if (is_file($sourcePath)) {
                $zip->addFile($sourcePath, 'phone-exhibit-tty/' . $ttyPhone . '-' . $id . '.wav');
            }
        }
    }

    $zip->close();

    if (!is_file($zipFile) || filesize($zipFile) === 0) {
        @unlink($zipFile);
        throw new RuntimeException('ZIP archive was not created.');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="phone-exhibit-wavs-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($zipFile));

    readfile($zipFile);
    @unlink($zipFile);
}

if (isset($_GET['download_config'])) {
    download_phone_config();
    exit;
}
if (isset($_GET['download_wavs'])) {
    download_wav_archive();
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $phoneNumbers = $_POST['phone_number'] ?? [];
        $ttyPhoneNumbers = $_POST['tty_phone_number'] ?? [];
        $shortNames = $_POST['short_name'] ?? [];

        if (!is_array($phoneNumbers)) {
            throw new RuntimeException('Invalid phone number data.');
        }

        $stmt = db()->prepare("
            UPDATE audio_files
            SET exhibit_phone_number = ?,
                tty_phone_number = ?,
                short_name = ?,
                updated_at = NOW()
            WHERE id = ?
              AND is_deleted = 0
        ");

        foreach ($phoneNumbers as $id => $phoneNumber) {
            $id = (int)$id;
            $phoneNumber = trim((string)$phoneNumber);
            $ttyPhoneNumber = trim((string)($ttyPhoneNumbers[$id] ?? ''));
            $shortName = trim((string)($shortNames[$id] ?? ''));

            foreach (['phone' => $phoneNumber, 'TTY phone' => $ttyPhoneNumber] as $label => $number) {
                if ($number !== '' && !preg_match('/^[0-9*#\- ]{1,20}$/', $number)) {
                    throw new RuntimeException("Invalid {$label} number for audio ID {$id}.");
                }
            }
            if (mb_strlen($shortName) > 120) {
                throw new RuntimeException("Short name too long for audio ID {$id}.");
            }

            $stmt->execute([
                $phoneNumber !== '' ? $phoneNumber : null,
                $ttyPhoneNumber !== '' ? $ttyPhoneNumber : null,
                $shortName !== '' ? $shortName : null,
                $id,
            ]);
        }

        $success = 'Phone numbers saved.';

        $settingKeys = [
            'recording_extension',
            'director_pin',
            'recording_pin_digits',
            'target_number_digits',
            'recording_min_silence_seconds',
            'recording_max_seconds',
            'recordings_pending_dir',
            'recording_enabled',

            'live_recording_enabled',
            'live_recording_extension',
            'live_director_pin',
            'live_recording_pin_digits',
            'live_target_number_digits',
            'live_recording_min_silence_seconds',
            'live_recording_max_seconds',
            'live_recordings_dir',
        ];

        foreach ($settingKeys as $key) {
            if (array_key_exists($key, $_POST)) {
                save_exhibit_setting($key, trim((string)$_POST[$key]));
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = get_exhibit_settings();

$defaults = [
    'recording_enabled' => '1',
    'recording_extension' => '7000',
    'director_pin' => '123456',
    'recording_pin_digits' => '6',
    'target_number_digits' => '7',
    'recording_min_silence_seconds' => '3',
    'recording_max_seconds' => '300',
    'recordings_pending_dir' => '/var/spool/asterisk/recordings/pending',

    'live_recording_enabled' => '1',
    'live_recording_extension' => '7100',
    'live_director_pin' => '654321',
    'live_recording_pin_digits' => '6',
    'live_target_number_digits' => '7',
    'live_recording_min_silence_seconds' => '3',
    'live_recording_max_seconds' => '300',
    'live_recordings_dir' => '/var/lib/asterisk/sounds/phone-exhibit-live',
];

$settings = array_merge($defaults, $settings);

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
        af.tty_phone_number,
        af.tty_relative_path,
        af.tty_status,
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
<p>
  Download:
    <a href="?download_config=1" class="button">Phone config</a>
    |
    <a href="?download_wavs=1" class="button">WAV archive</a>
</p>

<?php if ($success): ?>
    <div class="success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section style="border:1px solid #ddd;border-radius:12px;padding:14px;margin-bottom:18px;background:#fafafa;">
        <h2 style="margin-top:0;">Recording Portal Settings</h2>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <label>
                    <strong>Recording Enabled</strong><br>
                    <select name="recording_enabled" style="width:100%;">
                        <option value="1" <?= $settings['recording_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
                        <option value="0" <?= $settings['recording_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </label>

                <label>
                    <strong>Recording Extension</strong><br>
                    <input
                        type="text"
                        name="recording_extension"
                        value="<?= e($settings['recording_extension']) ?>"
                        pattern="[0-9]+"
                        style="width:100%;"
                        placeholder="7000"
                    >
                </label>

                <label>
                    <strong>Director PIN</strong><br>
                    <input
                        type="password"
                        name="director_pin"
                        value="<?= e($settings['director_pin']) ?>"
                        pattern="[0-9]+"
                        style="width:100%;"
                        autocomplete="new-password"
                    >
                </label>

                <label>
                    <strong>PIN Digits</strong><br>
                    <input
                        type="number"
                        name="recording_pin_digits"
                        value="<?= e($settings['recording_pin_digits']) ?>"
                        min="1"
                        max="12"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Target Number Digits</strong><br>
                    <input
                        type="number"
                        name="target_number_digits"
                        value="<?= e($settings['target_number_digits']) ?>"
                        min="1"
                        max="20"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Silence Stop Seconds</strong><br>
                    <input
                        type="number"
                        name="recording_min_silence_seconds"
                        value="<?= e($settings['recording_min_silence_seconds']) ?>"
                        min="1"
                        max="30"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Max Recording Seconds</strong><br>
                    <input
                        type="number"
                        name="recording_max_seconds"
                        value="<?= e($settings['recording_max_seconds']) ?>"
                        min="5"
                        max="1800"
                        style="width:100%;"
                    >
                </label>

                <label style="grid-column:1 / -1;">
                    <strong>Pending Recordings Directory</strong><br>
                    <input
                        type="text"
                        name="recordings_pending_dir"
                        value="<?= e($settings['recordings_pending_dir']) ?>"
                        style="width:100%;"
                        placeholder="/var/spool/asterisk/recordings/pending"
                    >
                </label>
            </div>

            <p style="margin-bottom:0;">
                <button type="submit">Save recording settings</button>
            </p>
    </section>
    <section style="border:1px solid #ddd;border-radius:12px;padding:14px;margin-bottom:18px;background:#f8fcff;">
        <h2 style="margin-top:0;">Live Recording Portal Settings</h2>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <label>
                    <strong>Live Recording Enabled</strong><br>
                    <select name="live_recording_enabled" style="width:100%;">
                        <option value="1" <?= $settings['live_recording_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
                        <option value="0" <?= $settings['live_recording_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
                    </select>
                </label>

                <label>
                    <strong>Live Recording Extension</strong><br>
                    <input
                        type="text"
                        name="live_recording_extension"
                        value="<?= e($settings['live_recording_extension']) ?>"
                        pattern="[0-9]+"
                        style="width:100%;"
                        placeholder="7100"
                    >
                </label>

                <label>
                    <strong>Live Director PIN</strong><br>
                    <input
                        type="password"
                        name="live_director_pin"
                        value="<?= e($settings['live_director_pin']) ?>"
                        pattern="[0-9]+"
                        style="width:100%;"
                        autocomplete="new-password"
                    >
                </label>

                <label>
                    <strong>Live PIN Digits</strong><br>
                    <input
                        type="number"
                        name="live_recording_pin_digits"
                        value="<?= e($settings['live_recording_pin_digits']) ?>"
                        min="1"
                        max="12"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Live Target Number Digits</strong><br>
                    <input
                        type="number"
                        name="live_target_number_digits"
                        value="<?= e($settings['live_target_number_digits']) ?>"
                        min="1"
                        max="20"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Live Silence Stop Seconds</strong><br>
                    <input
                        type="number"
                        name="live_recording_min_silence_seconds"
                        value="<?= e($settings['live_recording_min_silence_seconds']) ?>"
                        min="1"
                        max="30"
                        style="width:100%;"
                    >
                </label>

                <label>
                    <strong>Live Max Recording Seconds</strong><br>
                    <input
                        type="number"
                        name="live_recording_max_seconds"
                        value="<?= e($settings['live_recording_max_seconds']) ?>"
                        min="5"
                        max="1800"
                        style="width:100%;"
                    >
                </label>

                <label style="grid-column:1 / -1;">
                    <strong>Live Recordings Directory</strong><br>
                    <input
                        type="text"
                        name="live_recordings_dir"
                        value="<?= e($settings['live_recordings_dir']) ?>"
                        style="width:100%;"
                        placeholder="/var/lib/asterisk/sounds/phone-exhibit-live"
                    >
                </label>
            </div>

            <p style="margin-bottom:0;">
                <button type="submit">Save live recording settings</button>
            </p>
    </section>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Name</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">Phone #</th>
                <th style="text-align:left;border-bottom:1px solid #ddd;padding:6px;">TTY #</th>
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
                    <td style="border-bottom:1px solid #eee;padding:6px;vertical-align:middle;width:130px;">
                        <input
                            type="text"
                            name="tty_phone_number[<?= $id ?>]"
                            value="<?= e((string)($row['tty_phone_number'] ?? '')) ?>"
                            placeholder="201"
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