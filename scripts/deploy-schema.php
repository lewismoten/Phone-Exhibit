<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const SCHEMA_BOOTSTRAP_FILE = '011-schema-script-deployments.sql';

$projectRoot = dirname(__DIR__);
$schemaDir = $projectRoot . DIRECTORY_SEPARATOR . 'schema';

if (!is_dir($schemaDir)) {
    fwrite(STDERR, "Schema directory not found: {$schemaDir}\n");
    exit(1);
}

if (!defined('BASE_URL') || trim((string) BASE_URL) === '') {
    fwrite(STDERR, "BASE_URL must be configured in config.php.\n");
    exit(1);
}

if (!defined('SCHEMA_DEPLOY_API_TOKEN') || trim((string) SCHEMA_DEPLOY_API_TOKEN) === '') {
    fwrite(STDERR, "SCHEMA_DEPLOY_API_TOKEN must be configured in config.php.\n");
    exit(1);
}

$endpoint = rtrim((string) BASE_URL, '/') . '/api/deploy-schema.php';
$token = (string) SCHEMA_DEPLOY_API_TOKEN;

$schemaFiles = listSchemaFiles($schemaDir);
if ($schemaFiles === []) {
    fwrite(STDERR, "No schema files found in {$schemaDir}\n");
    exit(1);
}

$appliedCount = 0;

while (true) {
    $status = fetchSchemaStatus($endpoint, $token, $schemaFiles);
    $nextScript = $status['next_script'] ?? null;

    if (!is_string($nextScript) || $nextScript === '') {
        break;
    }

    applySchemaFile($schemaDir, $endpoint, $token, $nextScript);
    $appliedCount++;
}

echo $appliedCount === 0
    ? "Schema deploy complete. No pending files.\n"
    : "Schema deploy complete. {$appliedCount} file" . ($appliedCount === 1 ? '' : 's') . " applied.\n";

function applySchemaFile(string $schemaDir, string $endpoint, string $token, string $fileName): void
{
    $path = $schemaDir . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($path)) {
        fwrite(STDERR, "Schema file not found: {$fileName}\n");
        exit(1);
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read schema file: {$fileName}\n");
        exit(1);
    }

    $checksum = hash_file('sha256', $path);
    if ($checksum === false) {
        fwrite(STDERR, "Unable to hash schema file: {$fileName}\n");
        exit(1);
    }

    echo "Applying {$fileName}...\n";

    $result = postSchemaRequest($endpoint, $token, [
        'action' => 'apply',
        'script_name' => $fileName,
        'script_order' => (string) parseSchemaOrder($fileName),
        'script_checksum' => $checksum,
        'sql' => $sql,
    ]);

    if (!$result['success']) {
        fwrite(STDERR, "Schema endpoint: {$endpoint}\n");
        fwrite(STDERR, "Local token fingerprint: " . tokenFingerprint($token) . "\n");
        if (!empty($result['expected_token_fingerprint'])) {
            fwrite(STDERR, "Remote expected token fingerprint: {$result['expected_token_fingerprint']}\n");
        }
        if (!empty($result['received_token_fingerprint'])) {
            fwrite(STDERR, "Remote received token fingerprint: {$result['received_token_fingerprint']}\n");
        }
        fwrite(STDERR, "Failed {$fileName}: " . ($result['error'] ?? 'Unknown schema deploy error.') . "\n");
        exit(1);
    }

    echo "Applied {$fileName}\n";
}

function fetchSchemaStatus(string $endpoint, string $token, array $schemaFiles): array
{
    $status = postSchemaRequest($endpoint, $token, [
        'action' => 'status',
        'available_scripts' => $schemaFiles,
    ]);

    if ($status['success']) {
        return $status;
    }

    fwrite(STDERR, "Schema endpoint: {$endpoint}\n");
    fwrite(STDERR, "Local token fingerprint: " . tokenFingerprint($token) . "\n");
    if (!empty($status['expected_token_fingerprint'])) {
        fwrite(STDERR, "Remote expected token fingerprint: {$status['expected_token_fingerprint']}\n");
    }
    if (!empty($status['received_token_fingerprint'])) {
        fwrite(STDERR, "Remote received token fingerprint: {$status['received_token_fingerprint']}\n");
    }
    fwrite(STDERR, ($status['error'] ?? 'Unable to fetch remote schema status.') . "\n");
    exit(1);
}

function postSchemaRequest(string $endpoint, string $token, array $data): array
{
    $ch = curl_init();
    if ($ch === false) {
        return [
            'success' => false,
            'error' => 'Unable to initialize cURL.',
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
            'X-Schema-Deploy-Token: ' . $token,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'success' => false,
            'error' => $error !== '' ? $error : 'Schema deploy request failed.',
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'Invalid JSON response from schema deploy endpoint.',
            'raw' => $response,
            'status' => $httpCode,
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $decoded['success'] = false;
        $decoded['status'] = $httpCode;
    }

    return $decoded;
}

function listSchemaFiles(string $schemaDir): array
{
    $files = array_values(array_filter(
        scandir($schemaDir) ?: [],
        static fn (string $name): bool => preg_match('/^\d{3}-.+\.sql$/', $name) === 1
    ));

    sort($files, SORT_NATURAL);

    return $files;
}

function parseSchemaOrder(string $fileName): int
{
    if (preg_match('/^(\d{3})-/', $fileName, $matches) !== 1) {
        return 0;
    }

    return (int) $matches[1];
}

function tokenFingerprint(string $token): string
{
    if ($token === '') {
        return '(empty)';
    }

    return substr(hash('sha256', $token), 0, 12);
}
