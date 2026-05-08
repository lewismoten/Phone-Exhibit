<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

require_login();
require_current_terms_acceptance();

$resultUrl = null;
$error = '';
$ttyPreview = '';

function tty_friendly_text(string $text, int $width = 32): string
{
    $text = strtoupper($text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[^A-Z0-9 \n\-\?\:\(\)\.\,\/'=\+]+/", ' ', $text) ?? '';
    $text = preg_replace('/[ ]+/', ' ', $text) ?? '';

    $lines = explode("\n", trim($text));
    $wrapped = [];

    foreach ($lines as $line) {
        $wrapped[] = wordwrap(trim($line), $width, "\n", true);
    }

    return trim(implode("\n", $wrapped));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $message = (string)($_POST['message'] ?? '');
        $ttyPreview = tty_friendly_text($message);

        if ($ttyPreview === '') {
            throw new RuntimeException('Enter a message first.');
        }

        $user = current_user();
        $userId = (int)$user['id'];

        $dir = rtrim(UPLOAD_BASE_DIR, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $userId
            . DIRECTORY_SEPARATOR
            . 'tty-messages';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create output directory.');
        }

        $filename = 'tty-message-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.wav';
        $outputPath = $dir . DIRECTORY_SEPARATOR . $filename;

        $conversion = convert_text_for_tty($ttyPreview, $outputPath, true);

        if (!($conversion['success'] ?? false)) {
            throw new RuntimeException(
                "TTY conversion failed.\n"
                . "Exit code: " . ($conversion['exit_code'] ?? '') . "\n"
                . "Log: " . ($conversion['log'] ?? '')
            );
        }

        $relativePath = $userId . '/tty-messages/' . $filename;
        $resultUrl = upload_file_url($relativePath);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

html_header('Create TTY Audio');
?>

<h1>Create TTY Audio</h1>

<?php if ($error): ?>
    <div class="error"><?= nl2br(e($error)) ?></div>
<?php endif; ?>

<p>
    Type a message below. The preview shows a TTY-friendly version using uppercase letters,
    numbers, spaces, and limited punctuation.
</p>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <label for="message"><strong>Message</strong></label><br>
    <textarea
        id="message"
        name="message"
        rows="8"
        style="width:100%;max-width:800px;"
        placeholder="Type your TTY message here..."
    ><?= e((string)($_POST['message'] ?? '')) ?></textarea>

    <h2>TTY-friendly preview</h2>

    <pre
        id="ttyPreview"
        style="white-space:pre-wrap;background:#111;color:#0f0;padding:12px;border-radius:8px;max-width:800px;min-height:120px;"
    ><?= e($ttyPreview) ?></pre>

    <p style="font-size:13px;color:#666;">
        Allowed characters: A-Z, 0-9, space, - ? : ( ) . , / ' = +
        <br>
        Lines are wrapped at 32 characters.
    </p>

    <button type="submit">Generate TTY WAV</button>
</form>

<?php if ($resultUrl): ?>
    <hr>

    <h2>Generated Audio</h2>

    <audio controls style="width:100%;max-width:800px;">
        <source src="<?= e($resultUrl) ?>" type="audio/wav">
        Your browser does not support audio playback.
    </audio>

    <p>
        <a href="<?= e($resultUrl) ?>" download>Download TTY WAV</a>
    </p>
<?php endif; ?>

<script>
(function () {
    const input = document.getElementById('message');
    const preview = document.getElementById('ttyPreview');

    function ttyFriendly(text, width = 32) {
        text = text.toUpperCase();
        text = text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
        text = text.replace(/[^A-Z0-9 \n\-\?\:\(\)\.\,\/'=+]+/g, " ");
        text = text.replace(/[ ]+/g, " ");

        const lines = text.trim().split("\n");
        const out = [];

        for (const rawLine of lines) {
            let line = rawLine.trim();

            while (line.length > width) {
                let breakAt = line.lastIndexOf(" ", width);

                if (breakAt <= 0) {
                    breakAt = width;
                }

                out.push(line.slice(0, breakAt).trim());
                line = line.slice(breakAt).trim();
            }

            if (line.length) {
                out.push(line);
            }
        }

        return out.join("\n");
    }

    function updatePreview() {
        preview.textContent = ttyFriendly(input.value);
    }

    input.addEventListener('input', updatePreview);
    updatePreview();
})();
</script>

<?php html_footer(); ?>