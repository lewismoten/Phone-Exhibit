<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$projectConfigPath = $projectRoot . DIRECTORY_SEPARATOR . 'ftp.deploy.json';
$gitignorePath = $projectRoot . DIRECTORY_SEPARATOR . '.gitignore';
$secretConfigPath = $argv[1] ?? '~/ftp.regaldragondanceparty.com.json';
$secretConfigPath = expandHomePath($secretConfigPath);

if (!is_file($secretConfigPath)) {
    fwrite(STDERR, "Secret config file not found: {$secretConfigPath}\n");
    exit(1);
}

$secretConfigJson = file_get_contents($secretConfigPath);
if ($secretConfigJson === false) {
    fwrite(STDERR, "Unable to read secret config file: {$secretConfigPath}\n");
    exit(1);
}

$secretConfig = json_decode($secretConfigJson, true);
if (!is_array($secretConfig)) {
    fwrite(STDERR, "Invalid JSON in secret config file: {$secretConfigPath}\n");
    exit(1);
}

$projectConfig = [];
if (is_file($projectConfigPath)) {
    $projectConfigJson = file_get_contents($projectConfigPath);
    if ($projectConfigJson === false) {
        fwrite(STDERR, "Unable to read project config file: {$projectConfigPath}\n");
        exit(1);
    }

    $projectConfig = json_decode($projectConfigJson, true);
    if (!is_array($projectConfig)) {
        fwrite(STDERR, "Invalid JSON in project config file: {$projectConfigPath}\n");
        exit(1);
    }
}

$config = array_merge($projectConfig, $secretConfig);

$requiredSecretKeys = ['host', 'username', 'password'];
foreach ($requiredSecretKeys as $key) {
    if (!isset($secretConfig[$key]) || $secretConfig[$key] === '') {
        fwrite(STDERR, "Missing required secret config value: {$key}\n");
        exit(1);
    }
}

if (!isset($config['remotePath']) || $config['remotePath'] === '') {
    fwrite(STDERR, "Missing required deploy config value: remotePath\n");
    exit(1);
}

if (!function_exists('ftp_connect')) {
    fwrite(STDERR, "PHP FTP extension is not available.\n");
    exit(1);
}

$defaultExclude = [
    '.git',
    '.git/**',
    '.vscode',
    '.vscode/**',
    'README.md',
    '.gitignore',
    'Archive.zip',
    'error_log',
    'schema.sql',
    'scripts',
    'scripts/**',
    'logs',
    'logs/**',
    '.DS_Store',
    '*.example',
    'ftp.regaldragondanceparty.com.json.example',
    'ftp.deploy.json.example',
    'ftp.deploy.json',
];

$explicitExcludePatterns = array_values(array_unique(array_merge(
    $defaultExclude,
    isset($config['exclude']) && is_array($config['exclude']) ? $config['exclude'] : []
)));
$gitignoreExcludePatterns = loadGitignoreExcludePatterns($gitignorePath);

$port = isset($secretConfig['port']) ? (int) $secretConfig['port'] : 21;
$timeout = isset($secretConfig['timeout']) ? (int) $secretConfig['timeout'] : 90;
$useSsl = !empty($secretConfig['ssl']);
$passiveMode = !array_key_exists('passive', $secretConfig) || (bool) $secretConfig['passive'];
$dryRun = !empty($config['dryRun']);
$remoteRoot = normalizeRemotePath((string) $config['remotePath']);
$manifestPath = normalizeManifestPath((string) ($config['manifestPath'] ?? '.ftp-deploy-manifest.json'));
$remoteManifestPath = joinRemotePath($remoteRoot, $manifestPath);

$connection = $useSsl ? @ftp_ssl_connect($config['host'], $port, $timeout) : @ftp_connect($config['host'], $port, $timeout);
if ($connection === false) {
    fwrite(STDERR, "Unable to connect to {$config['host']}:{$port}\n");
    exit(1);
}

if (@ftp_login($connection, $config['username'], $config['password']) === false) {
    ftp_close($connection);
    fwrite(STDERR, "FTP login failed for {$config['username']}\n");
    exit(1);
}

ftp_pasv($connection, $passiveMode);

$paths = collectProjectPaths($projectRoot, $explicitExcludePatterns, $gitignoreExcludePatterns);
$files = collectFiles($projectRoot, $paths);
$remoteManifest = loadRemoteManifest($connection, $remoteManifestPath);
$remoteManifestFiles = isset($remoteManifest['files']) && is_array($remoteManifest['files']) ? $remoteManifest['files'] : [];
$remoteFailedFiles = isset($remoteManifest['failedFiles']) && is_array($remoteManifest['failedFiles']) ? $remoteManifest['failedFiles'] : [];
$nextManifestFiles = [];
$nextFailedFiles = [];
$filesToUpload = [];
$deferredFailedUploads = [];
$uploadedManifestFiles = [];
$manifestFlushInterval = 10;
$lastManifestFlushAt = time();
$deployStartedAt = gmdate(DATE_ATOM);
$lastCheckpointAt = null;
$deployCompletedAt = null;
$manifestErrors = [];

echo $dryRun ? "Dry run only. No files will be uploaded.\n" : "Deploying files to {$config['host']}...\n";

foreach ($files as $relativePath) {
    $localPath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    $localManifestEntry = buildManifestEntry($localPath);
    $nextManifestFiles[$relativePath] = $localManifestEntry;
    $remoteManifestEntry = $remoteManifestFiles[$relativePath] ?? null;

    if (manifestEntriesMatch($localManifestEntry, $remoteManifestEntry)) {
        $uploadedManifestFiles[$relativePath] = $localManifestEntry;
        echo "Skipped {$relativePath}\n";
        continue;
    }

    if (shouldDeferFailedFile($relativePath, $localManifestEntry, $remoteFailedFiles)) {
        $deferredFailedUploads[] = $relativePath;
        $nextFailedFiles[$relativePath] = $localManifestEntry;
        echo "Deferred {$relativePath}\n";
        continue;
    }

    $filesToUpload[] = $relativePath;
}

foreach (array_merge($filesToUpload, $deferredFailedUploads) as $relativePath) {
    $localPath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    $remotePath = joinRemotePath($remoteRoot, $relativePath);
    $localManifestEntry = $nextManifestFiles[$relativePath];

    if ($dryRun) {
        echo "PUT {$relativePath} -> {$remotePath}\n";
        continue;
    }

    ensureRemoteDirectory($connection, dirname($remotePath));

    if (!ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
        $manifestErrors[] = buildManifestError('upload_failed', "Upload failed: {$relativePath}", $relativePath);
        $nextFailedFiles[$relativePath] = $localManifestEntry;
        $manifestSaved = flushManifestSnapshot(
            $connection,
            $remoteManifestPath,
            $uploadedManifestFiles,
            $nextFailedFiles,
            true,
            buildManifestMeta($deployStartedAt, gmdate(DATE_ATOM), null, $manifestErrors)
        );
        ftp_close($connection);
        if ($manifestSaved) {
            fwrite(STDERR, "Manifest checkpoint saved before aborting.\n");
        } else {
            fwrite(STDERR, "Manifest checkpoint could not be saved before aborting.\n");
        }
        fwrite(STDERR, "Upload failed: {$relativePath}\n");
        exit(1);
    }

    $uploadedManifestFiles[$relativePath] = $localManifestEntry;
    unset($nextFailedFiles[$relativePath]);
    echo "Uploaded {$relativePath}\n";

    if (time() - $lastManifestFlushAt >= $manifestFlushInterval) {
        $lastCheckpointAt = gmdate(DATE_ATOM);
        if (flushManifestSnapshot(
            $connection,
            $remoteManifestPath,
            $uploadedManifestFiles,
            $nextFailedFiles,
            true,
            buildManifestMeta($deployStartedAt, $lastCheckpointAt, null, $manifestErrors)
        )) {
            echo "Checkpointed manifest {$remoteManifestPath}\n";
            $lastManifestFlushAt = time();
        } else {
            $manifestErrors[] = buildManifestError(
                'manifest_checkpoint_failed',
                "Unable to checkpoint manifest during deploy.",
                $remoteManifestPath
            );
            fwrite(STDERR, "Warning: unable to checkpoint manifest during deploy.\n");
        }
    }
}

$shouldUploadManifest = !isset($remoteManifest['files'])
    || $remoteManifest['files'] !== $nextManifestFiles
    || $remoteFailedFiles !== $nextFailedFiles
    || count($filesToUpload) > 0
    || count($deferredFailedUploads) > 0;

if ($shouldUploadManifest) {
    if ($dryRun) {
        echo "PUT manifest -> {$remoteManifestPath}\n";
    } else {
        ensureRemoteDirectory($connection, dirname($remoteManifestPath));
        $deployCompletedAt = gmdate(DATE_ATOM);
        if (!flushManifestSnapshot(
            $connection,
            $remoteManifestPath,
            $nextManifestFiles,
            $nextFailedFiles,
            false,
            buildManifestMeta($deployStartedAt, $lastCheckpointAt, $deployCompletedAt, $manifestErrors)
        )) {
            ftp_close($connection);
            fwrite(STDERR, "Unable to update manifest {$remoteManifestPath}\n");
            exit(1);
        }
        echo "Updated manifest {$remoteManifestPath}\n";
    }
} else {
    echo "Manifest unchanged\n";
}

ftp_close($connection);
echo $dryRun ? "Dry run complete.\n" : "Upload complete.\n";

function expandHomePath(string $path): string
{
    if ($path === '~') {
        return (string) getenv('HOME');
    }

    if (str_starts_with($path, '~/')) {
        return rtrim((string) getenv('HOME'), '/') . substr($path, 1);
    }

    return $path;
}

function normalizeRemotePath(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/';
    }

    $path = '/' . trim($path, '/');
    return $path === '/' ? '/' : rtrim($path, '/');
}

function joinRemotePath(string $base, string $relativePath): string
{
    $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
    if ($base === '/') {
        return '/' . ltrim($relativePath, '/');
    }

    return $base . '/' . ltrim($relativePath, '/');
}

function collectProjectPaths(string $projectRoot, array $explicitExcludePatterns, array $gitignoreExcludePatterns): array
{
    $paths = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $fullPath = $item->getPathname();
        $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $fullPath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        if (shouldExclude($relativePath, $explicitExcludePatterns, $gitignoreExcludePatterns)) {
            continue;
        }

        $paths[] = $relativePath;
    }

    sort($paths);
    return $paths;
}

function shouldExclude(string $relativePath, array $explicitExcludePatterns, array $gitignoreExcludePatterns): bool
{
    if (matchesAnyPattern($relativePath, $explicitExcludePatterns)) {
        return true;
    }

    if ($relativePath === 'lib' || str_starts_with($relativePath, 'lib/')) {
        return false;
    }

    return matchesAnyPattern($relativePath, $gitignoreExcludePatterns);
}

function matchesAnyPattern(string $relativePath, array $patterns): bool
{
    foreach ($patterns as $pattern) {
        if (matchesPattern($relativePath, $pattern)) {
            return true;
        }
    }

    return false;
}

function matchesPattern(string $relativePath, string $pattern): bool
{
    $normalized = trim(str_replace('\\', '/', $pattern));
    if ($normalized === '') {
        return false;
    }

    $anchored = str_starts_with($normalized, '/');
    $normalized = ltrim($normalized, '/');

    if (str_ends_with($normalized, '/')) {
        $normalized = rtrim($normalized, '/');
        return $relativePath === $normalized || str_starts_with($relativePath, $normalized . '/');
    }

    if (str_ends_with($normalized, '/**')) {
        $prefix = substr($normalized, 0, -3);
        return $relativePath === $prefix || str_starts_with($relativePath, $prefix . '/');
    }

    if (str_contains($normalized, '*') || str_contains($normalized, '?')) {
        if (fnmatch($normalized, $relativePath, FNM_PATHNAME)) {
            return true;
        }

        return !$anchored && fnmatch('*/' . $normalized, $relativePath, FNM_PATHNAME);
    }

    if ($relativePath === $normalized) {
        return true;
    }

    return !$anchored && str_ends_with($relativePath, '/' . $normalized);
}

function loadGitignoreExcludePatterns(string $gitignorePath): array
{
    if (!is_file($gitignorePath)) {
        return [];
    }

    $lines = file($gitignorePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $patterns = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '!')) {
            continue;
        }

        $normalized = str_replace('\\', '/', $line);
        if ($normalized === '/lib' || $normalized === '/lib/' || $normalized === 'lib' || $normalized === 'lib/') {
            continue;
        }

        $patterns[] = $normalized;
    }

    return $patterns;
}

function collectFiles(string $projectRoot, array $paths): array
{
    $files = [];

    foreach ($paths as $relativePath) {
        if (is_file($projectRoot . DIRECTORY_SEPARATOR . $relativePath)) {
            $files[] = $relativePath;
        }
    }

    return $files;
}

function ensureRemoteDirectory($connection, string $remoteDirectory): void
{
    $remoteDirectory = normalizeRemotePath($remoteDirectory);

    if ($remoteDirectory === '/') {
        return;
    }

    $segments = explode('/', ltrim($remoteDirectory, '/'));
    $current = '';

    foreach ($segments as $segment) {
        $current .= '/' . $segment;
        @ftp_mkdir($connection, $current);
    }
}

function normalizeManifestPath(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = ltrim($path, '/');

    return $path === '' ? '.ftp-deploy-manifest.json' : $path;
}

function loadRemoteManifest($connection, string $remoteManifestPath): array
{
    $tempFile = tempnam(sys_get_temp_dir(), 'ftp-manifest-');
    if ($tempFile === false) {
        return [];
    }

    try {
        if (!@ftp_get($connection, $tempFile, $remoteManifestPath, FTP_BINARY)) {
            return [];
        }

        $manifestJson = file_get_contents($tempFile);
        if ($manifestJson === false || trim($manifestJson) === '') {
            return [];
        }

        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest)) {
            fwrite(STDERR, "Remote manifest is invalid JSON. Rebuilding {$remoteManifestPath}.\n");
            return [];
        }

        return $manifest;
    } finally {
        @unlink($tempFile);
    }
}

function buildManifestEntry(string $localPath): array
{
    $hash = sha1_file($localPath);
    if ($hash === false) {
        fwrite(STDERR, "Unable to hash file: {$localPath}\n");
        exit(1);
    }

    $size = filesize($localPath);
    if ($size === false) {
        fwrite(STDERR, "Unable to read file size: {$localPath}\n");
        exit(1);
    }

    return [
        'sha1' => $hash,
        'size' => $size,
    ];
}

function manifestEntriesMatch(array $localEntry, $remoteEntry): bool
{
    if (!is_array($remoteEntry)) {
        return false;
    }

    return ($remoteEntry['sha1'] ?? null) === $localEntry['sha1']
        && (int) ($remoteEntry['size'] ?? -1) === $localEntry['size'];
}

function shouldDeferFailedFile(string $relativePath, array $localManifestEntry, array $remoteFailedFiles): bool
{
    if (!isset($remoteFailedFiles[$relativePath]) || !is_array($remoteFailedFiles[$relativePath])) {
        return false;
    }

    return manifestEntriesMatch($localManifestEntry, $remoteFailedFiles[$relativePath]);
}

function flushManifestSnapshot(
    $connection,
    string $remoteManifestPath,
    array $manifestFiles,
    array $failedFiles,
    bool $partial,
    array $meta
): bool
{
    $manifestPayload = json_encode([
        'generatedAt' => gmdate(DATE_ATOM),
        'startedAt' => $meta['startedAt'],
        'lastCheckpointAt' => $meta['lastCheckpointAt'],
        'completedAt' => $meta['completedAt'],
        'isPartial' => $partial,
        'errors' => $meta['errors'],
        'files' => $manifestFiles,
        'failedFiles' => $failedFiles,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($manifestPayload === false) {
        return false;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'ftp-manifest-');
    if ($tempFile === false) {
        return false;
    }

    try {
        if (file_put_contents($tempFile, $manifestPayload) === false) {
            return false;
        }

        return ftp_put($connection, $remoteManifestPath, $tempFile, FTP_BINARY);
    } finally {
        @unlink($tempFile);
    }
}

function buildManifestMeta(string $startedAt, ?string $lastCheckpointAt, ?string $completedAt, array $errors): array
{
    return [
        'startedAt' => $startedAt,
        'lastCheckpointAt' => $lastCheckpointAt,
        'completedAt' => $completedAt,
        'errors' => array_values($errors),
    ];
}

function buildManifestError(string $code, string $message, string $path): array
{
    return [
        'code' => $code,
        'message' => $message,
        'path' => $path,
        'timestamp' => gmdate(DATE_ATOM),
    ];
}
