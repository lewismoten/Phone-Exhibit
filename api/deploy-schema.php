<?php
declare(strict_types=1);

require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.',
    ], JSON_THROW_ON_ERROR);
    exit;
}

$token = request_bearer_token();
$configuredToken = defined('SCHEMA_DEPLOY_API_TOKEN') ? (string) SCHEMA_DEPLOY_API_TOKEN : '';

if ($configuredToken === '' || $token === null || !hash_equals($configuredToken, $token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid schema deployment token.',
        'expected_token_fingerprint' => token_fingerprint($configuredToken),
        'received_token_fingerprint' => token_fingerprint($token),
    ], JSON_THROW_ON_ERROR);
    exit;
}

$action = trim((string) ($_POST['action'] ?? 'status'));

try {
    if ($action === 'status') {
        echo json_encode(schema_deploy_status(), JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'apply') {
        echo json_encode(schema_apply_script(), JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action.',
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error->getMessage(),
    ], JSON_THROW_ON_ERROR);
}

function schema_deploy_status(): array
{
    $availableScripts = schema_available_scripts_from_request();
    $bootstrapScript = '011-schema-script-deployments.sql';
    $trackerExists = schema_tracker_table_exists();
    $entries = [];

    if ($trackerExists) {
        $statement = db()->query(
            'SELECT script_name, script_order, script_checksum, status, attempted_at, completed_at, error_message
             FROM schema_script_deployments
             ORDER BY id ASC'
        );

        foreach ($statement->fetchAll() as $row) {
            $entries[(string) $row['script_name']] = [
                'script_name' => (string) $row['script_name'],
                'script_order' => isset($row['script_order']) ? (int) $row['script_order'] : null,
                'script_checksum' => (string) ($row['script_checksum'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'attempted_at' => (string) ($row['attempted_at'] ?? ''),
                'completed_at' => (string) ($row['completed_at'] ?? ''),
                'error_message' => (string) ($row['error_message'] ?? ''),
            ];
        }
    }

    $nextScript = null;

    if (!$trackerExists) {
        $nextScript = in_array($bootstrapScript, $availableScripts, true) ? $bootstrapScript : null;
    } else {
        foreach ($availableScripts as $scriptName) {
            if ($scriptName === $bootstrapScript) {
                continue;
            }

            $entry = $entries[$scriptName] ?? null;
            if (($entry['status'] ?? null) !== 'succeeded') {
                $nextScript = $scriptName;
                break;
            }
        }
    }

    return [
        'success' => true,
        'tracker_exists' => $trackerExists,
        'bootstrap_script' => $bootstrapScript,
        'next_script' => $nextScript,
        'entries' => array_values($entries),
    ];
}

function schema_apply_script(): array
{
    $scriptName = basename((string) ($_POST['script_name'] ?? ''));
    $scriptOrder = isset($_POST['script_order']) ? (int) $_POST['script_order'] : null;
    $scriptChecksum = trim((string) ($_POST['script_checksum'] ?? ''));
    $sql = (string) ($_POST['sql'] ?? '');
    $bootstrapScript = '011-schema-script-deployments.sql';

    if ($scriptName === '' || $sql === '') {
        http_response_code(400);
        return [
            'success' => false,
            'error' => 'Missing script_name or sql.',
        ];
    }

    if (preg_match('/^\d{3}-.+\.sql$/', $scriptName) !== 1) {
        http_response_code(400);
        return [
            'success' => false,
            'error' => 'Invalid schema script name.',
        ];
    }

    $trackerExists = schema_tracker_table_exists();
    if (!$trackerExists && $scriptName !== $bootstrapScript) {
        http_response_code(409);
        return [
            'success' => false,
            'error' => 'Schema tracker table does not exist. Apply 011-schema-script-deployments.sql first.',
        ];
    }

    $deploymentId = null;

    if ($trackerExists && $scriptName !== $bootstrapScript) {
        $deploymentId = schema_record_started($scriptName, $scriptOrder, $scriptChecksum);
    }

    try {
        schema_execute_sql($sql);

        if (!$trackerExists && $scriptName === $bootstrapScript) {
            $trackerExists = schema_tracker_table_exists();
        }

        if ($trackerExists) {
            if ($scriptName === $bootstrapScript) {
                $existingId = schema_find_deployment_id($scriptName);
                if ($existingId !== null) {
                    schema_record_finished($existingId, 'succeeded', null);
                }
            } elseif ($deploymentId !== null) {
                schema_record_finished($deploymentId, 'succeeded', null);
            }
        }

        return [
            'success' => true,
            'script_name' => $scriptName,
            'tracker_exists' => $trackerExists,
        ];
    } catch (Throwable $error) {
        if ($trackerExists && $deploymentId !== null) {
            schema_record_finished($deploymentId, 'failed', $error->getMessage());
        }

        throw $error;
    }
}

function schema_tracker_table_exists(): bool
{
    $statement = db()->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name = ?
         LIMIT 1'
    );
    $statement->execute([DB_NAME, 'schema_script_deployments']);

    return (bool) $statement->fetchColumn();
}

function schema_record_started(string $scriptName, ?int $scriptOrder, string $scriptChecksum): int
{
    $statement = db()->prepare(
        'INSERT INTO schema_script_deployments (
            script_name,
            script_order,
            script_checksum,
            status
        ) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            script_order = VALUES(script_order),
            script_checksum = VALUES(script_checksum),
            status = VALUES(status),
            attempted_at = CURRENT_TIMESTAMP,
            completed_at = NULL,
            error_message = NULL'
    );

    $statement->execute([
        $scriptName,
        $scriptOrder,
        $scriptChecksum === '' ? null : $scriptChecksum,
        'started',
    ]);

    $id = schema_find_deployment_id($scriptName);
    if ($id === null) {
        throw new RuntimeException('Unable to determine schema deployment record ID.');
    }

    return $id;
}

function schema_record_finished(int $deploymentId, string $status, ?string $errorMessage): void
{
    $statement = db()->prepare(
        'UPDATE schema_script_deployments
         SET status = ?,
             completed_at = CURRENT_TIMESTAMP,
             error_message = ?
         WHERE id = ?'
    );
    $statement->execute([$status, $errorMessage, $deploymentId]);
}

function schema_find_deployment_id(string $scriptName): ?int
{
    $statement = db()->prepare(
        'SELECT id
         FROM schema_script_deployments
         WHERE script_name = ?
         LIMIT 1'
    );
    $statement->execute([$scriptName]);
    $value = $statement->fetchColumn();

    return $value === false ? null : (int) $value;
}

function schema_execute_sql(string $sql): void
{
    foreach (schema_split_sql_statements($sql) as $statement) {
        $trimmed = trim($statement);
        if ($trimmed === '') {
            continue;
        }

        if (schema_should_skip_statement($trimmed)) {
            continue;
        }

        db()->exec($statement);
    }
}

function schema_split_sql_statements(string $sql): array
{
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $lines = explode("\n", $sql);
    $delimiter = ';';
    $buffer = '';
    $statements = [];

    foreach ($lines as $line) {
        $trimmed = ltrim($line);

        if (preg_match('/^DELIMITER\s+(\S+)$/i', $trimmed, $matches) === 1) {
            if (trim($buffer) !== '') {
                $statements[] = trim(schema_remove_trailing_delimiter($buffer, $delimiter));
                $buffer = '';
            }

            $delimiter = $matches[1];
            continue;
        }

        $buffer .= $line . "\n";

        if (schema_statement_ends_with_delimiter($buffer, $delimiter)) {
            $statements[] = trim(schema_remove_trailing_delimiter($buffer, $delimiter));
            $buffer = '';
        }
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function schema_statement_ends_with_delimiter(string $buffer, string $delimiter): bool
{
    $trimmed = rtrim($buffer);

    return $trimmed !== '' && str_ends_with($trimmed, $delimiter);
}

function schema_remove_trailing_delimiter(string $buffer, string $delimiter): string
{
    $trimmed = rtrim($buffer);

    if (!str_ends_with($trimmed, $delimiter)) {
        return $buffer;
    }

    return substr($trimmed, 0, -strlen($delimiter));
}

function schema_should_skip_statement(string $statement): bool
{
    return preg_match('/^CREATE\s+DATABASE\b/i', $statement) === 1
        || preg_match('/^USE\b/i', $statement) === 1;
}

function token_fingerprint(?string $token): string
{
    if (!is_string($token) || $token === '') {
        return '(empty)';
    }

    return substr(hash('sha256', $token), 0, 12);
}

function schema_available_scripts_from_request(): array
{
    $raw = $_POST['available_scripts'] ?? [];

    if (!is_array($raw)) {
        $raw = [$raw];
    }

    $scripts = [];

    foreach ($raw as $value) {
        $script = basename((string) $value);
        if (preg_match('/^\d{3}-.+\.sql$/', $script) !== 1) {
            continue;
        }

        $scripts[] = $script;
    }

    $scripts = array_values(array_unique($scripts));
    sort($scripts, SORT_NATURAL);

    return $scripts;
}
