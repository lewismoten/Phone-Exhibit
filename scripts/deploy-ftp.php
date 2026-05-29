<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$projectConfigPath = $projectRoot . DIRECTORY_SEPARATOR . 'ftp.deploy.json';
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
    'Archive.zip',
    'error_log',
    'logs',
    'logs/**',
    '.DS_Store',
    'ftp.regaldragondanceparty.com.json.example',
    'ftp.deploy.json.example',
];

$excludePatterns = array_values(array_unique(array_merge(
    $defaultExclude,
    isset($config['exclude']) && is_array($config['exclude']) ? $config['exclude'] : []
)));

$port = isset($secretConfig['port']) ? (int) $secretConfig['port'] : 21;
$timeout = isset($secretConfig['timeout']) ? (int) $secretConfig['timeout'] : 90;
$useSsl = !empty($secretConfig['ssl']);
$passiveMode = !array_key_exists('passive', $secretConfig) || (bool) $secretConfig['passive'];
$dryRun = !empty($config['dryRun']);
$remoteRoot = normalizeRemotePath((string) $config['remotePath']);

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

$paths = collectProjectPaths($projectRoot, $excludePatterns);
[$directories, $files] = splitPaths($paths);

echo $dryRun ? "Dry run only. No files will be uploaded.\n" : "Uploading files to {$config['host']}...\n";

foreach ($directories as $relativePath) {
    $remoteDirectory = joinRemotePath($remoteRoot, $relativePath);
    if ($dryRun) {
        echo "MKDIR {$remoteDirectory}\n";
        continue;
    }

    ensureRemoteDirectory($connection, $remoteDirectory);
}

foreach ($files as $relativePath) {
    $localPath = $projectRoot . DIRECTORY_SEPARATOR . $relativePath;
    $remotePath = joinRemotePath($remoteRoot, $relativePath);

    if ($dryRun) {
        echo "PUT {$relativePath} -> {$remotePath}\n";
        continue;
    }

    ensureRemoteDirectory($connection, dirname($remotePath));

    if (!ftp_put($connection, $remotePath, $localPath, FTP_BINARY)) {
        ftp_close($connection);
        fwrite(STDERR, "Upload failed: {$relativePath}\n");
        exit(1);
    }

    echo "Uploaded {$relativePath}\n";
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

function collectProjectPaths(string $projectRoot, array $excludePatterns): array
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

        if (shouldExclude($relativePath, $excludePatterns)) {
            continue;
        }

        $paths[] = $relativePath;
    }

    sort($paths);
    return $paths;
}

function shouldExclude(string $relativePath, array $excludePatterns): bool
{
    foreach ($excludePatterns as $pattern) {
        $normalized = trim(str_replace('\\', '/', $pattern));

        if ($normalized === '') {
            continue;
        }

        if (str_ends_with($normalized, '/**')) {
            $prefix = substr($normalized, 0, -3);
            if ($relativePath === $prefix || str_starts_with($relativePath, $prefix . '/')) {
                return true;
            }
            continue;
        }

        if ($relativePath === rtrim($normalized, '/')) {
            return true;
        }
    }

    return false;
}

function splitPaths(array $paths): array
{
    $directories = [];
    $files = [];

    foreach ($paths as $relativePath) {
        if (is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativePath)) {
            $directories[] = $relativePath;
            continue;
        }

        $files[] = $relativePath;
    }

    return [$directories, $files];
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
