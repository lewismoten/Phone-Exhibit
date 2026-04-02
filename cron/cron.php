<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

log_line("Master cron starting...");

/**
 * Simple job runner
 */
function run_job(string $name, callable $fn): void
{
    log_line("---- Running: {$name}");

    try {
        $fn();
        log_line("---- Completed: {$name}");
    } catch (Throwable $e) {
        log_line("---- Failed: {$name} | {$e->getMessage()}");
    }
}

run_job('audio conversions', function () {
    require_once __DIR__ . '/process-audio-conversions.php';
    run_audio_conversion_worker();
});

run_job('transcriptions', function () {
    require_once __DIR__ . '/process-transcriptions.php';
    run_transcription_worker();
});


run_job('tty', function () {
    require_once __DIR__ . '/process-tty.php';
    run_tty_worker();
});
log_line("Master cron done.");