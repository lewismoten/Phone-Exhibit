<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Master cron starting...\n";

/**
 * Simple job runner
 */
function run_job(string $name, callable $fn): void
{
    echo "---- Running: {$name}\n";

    try {
        $fn();
        echo "---- Completed: {$name}\n";
    } catch (Throwable $e) {
        echo "---- Failed: {$name} | {$e->getMessage()}\n";
    }
}

/**
 * Jobs
 */
run_job('audio conversions', function () {
    require __DIR__ . '/process-audio-conversions.php';
});

// future jobs can go here:
// run_job('cleanup', fn() => require __DIR__ . '/cleanup.php');
// run_job('email queue', fn() => require __DIR__ . '/email-queue.php');

echo "Master cron done.\n";