<?php

declare(strict_types=1);

const LIGHTHOUSE_SETTINGS_FILE = __DIR__ . '/../settings.json';
const LIGHTHOUSE_TABLE_MONITORS = 'lighthouse_monitors';
const LIGHTHOUSE_TABLE_RUNS = 'lighthouse_runs';
const LIGHTHOUSE_TABLE_RUN_VALUES = 'lighthouse_run_values';

const LIGHTHOUSE_DB_HOST = '127.0.0.1';
const LIGHTHOUSE_DB_PORT = 3306;
const LIGHTHOUSE_DB_NAME = 'lighthouse';
const LIGHTHOUSE_DB_USER = 'root';
const LIGHTHOUSE_DB_PASSWORD = '';

function lighthouse_send_json(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function lighthouse_assert_method(array $allowedMethods): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, $allowedMethods, true)) {
        lighthouse_send_json(405, ['error' => 'Method not allowed']);
    }
}

function lighthouse_load_settings(): array
{
    if (!file_exists(LIGHTHOUSE_SETTINGS_FILE)) {
        lighthouse_send_json(500, ['error' => 'settings.json not found']);
    }

    $raw = file_get_contents(LIGHTHOUSE_SETTINGS_FILE);
    $settings = json_decode($raw ?: '', true);

    if (!is_array($settings)) {
        lighthouse_send_json(500, ['error' => 'Invalid settings.json']);
    }

    return $settings;
}

function lighthouse_require_setting(array $settings, string $key): string
{
    $value = trim((string)($settings[$key] ?? ''));
    if ($value === '') {
        lighthouse_send_json(400, ['error' => sprintf('%s is missing in settings', $key)]);
    }

    return $value;
}

function lighthouse_http_get_json(string $url, string $token): array
{
    $headers = [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ];

    $trimmedUrl = preg_replace('/\s+/', '', $url) ?? $url;

    if (function_exists('curl_init')) {
        $ch = curl_init($trimmedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => 'GET'
        ]);

        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'error' => $error !== '' ? $error : 'Request failed'];
        }

        $decoded = json_decode((string)$body, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'status' => $status, 'error' => 'Invalid JSON response'];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'data' => $decoded
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $body = @file_get_contents($trimmedUrl, false, $context);
    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
            $status = (int)$matches[1];
        }
    }

    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'error' => 'Request failed'];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'status' => $status, 'error' => 'Invalid JSON response'];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'data' => $decoded
    ];
}

function lighthouse_extract_ids_from_payload(mixed $data, array $idKeys): array
{
    $ids = [];

    $visit = function (mixed $node) use (&$visit, &$ids, $idKeys): void {
        if (!is_array($node)) {
            return;
        }

        foreach ($idKeys as $key) {
            if (isset($node[$key]) && is_scalar($node[$key])) {
                $ids[] = (string)$node[$key];
            }
        }

        foreach ($node as $value) {
            $visit($value);
        }
    };

    $visit($data);

    return array_values(array_unique(array_filter(array_map('trim', $ids), static fn (string $value): bool => $value !== '')));
}

function lighthouse_filter_uuid_ids(array $ids): array
{
    return array_values(array_filter(
        array_values(array_unique(array_map('trim', $ids))),
        static fn (string $value): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1
    ));
}

function lighthouse_database_settings(): array
{
    $host = trim((string) LIGHTHOUSE_DB_HOST);
    $port = (int) LIGHTHOUSE_DB_PORT;
    $name = trim((string) LIGHTHOUSE_DB_NAME);
    $user = (string) LIGHTHOUSE_DB_USER;
    $password = (string) LIGHTHOUSE_DB_PASSWORD;

    if ($host === '' || $name === '') {
        lighthouse_send_json(500, ['error' => 'Database settings are incomplete']);
    }

    if ($port <= 0 || $port > 65535) {
        lighthouse_send_json(500, ['error' => 'dbPort must be between 1 and 65535']);
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
        lighthouse_send_json(500, ['error' => 'dbName can only contain letters, numbers, and underscores']);
    }

    return [
        'host' => $host,
        'port' => $port,
        'name' => $name,
        'user' => $user,
        'password' => $password
    ];
}

function lighthouse_dataset_tables(): array
{
    return [
        LIGHTHOUSE_TABLE_MONITORS,
        LIGHTHOUSE_TABLE_RUNS,
        LIGHTHOUSE_TABLE_RUN_VALUES
    ];
}

function lighthouse_assert_dataset_table(string $table): string
{
    if (!in_array($table, lighthouse_dataset_tables(), true)) {
        lighthouse_send_json(500, ['error' => 'Invalid dataset table requested']);
    }

    return $table;
}

function lighthouse_db_connect(array $settings): PDO
{
    if (!class_exists('PDO')) {
        lighthouse_send_json(500, ['error' => 'PDO extension is not available']);
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        lighthouse_send_json(500, ['error' => 'PDO MySQL driver is not available']);
    }

    $db = lighthouse_database_settings();
    $bootstrapDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);

    try {
        $bootstrap = new PDO($bootstrapDsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        $safeDatabaseName = str_replace('`', '``', $db['name']);
        $bootstrap->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $safeDatabaseName
        ));
    } catch (PDOException $exception) {
        lighthouse_send_json(500, [
            'error' => 'Database bootstrap failed',
            'details' => $exception->getMessage()
        ]);
    }

    try {
        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $exception) {
        lighthouse_send_json(500, [
            'error' => 'Database connection failed',
            'details' => $exception->getMessage()
        ]);
    }

    throw new RuntimeException('Database connection failed unexpectedly');
}

function lighthouse_db_ensure_tables(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . LIGHTHOUSE_TABLE_MONITORS . ' (
            monitor_id VARCHAR(64) NOT NULL,
            name VARCHAR(255) NULL,
            url TEXT NULL,
            version VARCHAR(32) NULL,
            region VARCHAR(64) NULL,
            device VARCHAR(32) NULL,
            headers_json LONGTEXT NULL,
            updated_at_ms BIGINT NULL,
            created_at_ms BIGINT NULL,
            synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (monitor_id),
            INDEX idx_lighthouse_monitors_region (region)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . LIGHTHOUSE_TABLE_RUNS . ' (
            run_id VARCHAR(64) NOT NULL,
            monitor_id VARCHAR(64) NULL,
            region VARCHAR(64) NULL,
            state VARCHAR(64) NULL,
            created_at_ms BIGINT NULL,
            api_status INT NOT NULL DEFAULT 0,
            api_ok TINYINT(1) NOT NULL DEFAULT 0,
            api_error TEXT NULL,
            synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (run_id),
            INDEX idx_lighthouse_runs_monitor (monitor_id),
            INDEX idx_lighthouse_runs_created (created_at_ms)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . LIGHTHOUSE_TABLE_RUN_VALUES . ' (
            run_id VARCHAR(64) NOT NULL,
            version VARCHAR(32) NULL,
            url TEXT NULL,
            device VARCHAR(32) NULL,
            region VARCHAR(64) NULL,
            state VARCHAR(64) NULL,
            performance DOUBLE NULL,
            accessibility DOUBLE NULL,
            best_practices DOUBLE NULL,
            seo DOUBLE NULL,
            fcp DOUBLE NULL,
            si DOUBLE NULL,
            lcp DOUBLE NULL,
            tti DOUBLE NULL,
            tbt DOUBLE NULL,
            cls DOUBLE NULL,
            created_at_ms BIGINT NULL,
            api_status INT NOT NULL DEFAULT 0,
            api_ok TINYINT(1) NOT NULL DEFAULT 0,
            api_error TEXT NULL,
            synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (run_id),
            INDEX idx_lighthouse_run_values_created (created_at_ms)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

}

function lighthouse_db_initialize(array $settings): PDO
{
    $pdo = lighthouse_db_connect($settings);
    lighthouse_db_ensure_tables($pdo);

    return $pdo;
}

function lighthouse_json_encode_or_fail(mixed $value): string
{
    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        lighthouse_send_json(500, ['error' => 'Failed to encode dataset for storage']);
    }

    return $encoded;
}

function lighthouse_scalar_string_or_null(mixed $value): ?string
{
    if (!is_scalar($value) || trim((string)$value) === '') {
        return null;
    }

    return (string)$value;
}

function lighthouse_scalar_int_or_null(mixed $value): ?int
{
    if (!is_numeric($value)) {
        return null;
    }

    return (int)$value;
}

function lighthouse_scalar_float_or_null(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    return (float)$value;
}

function lighthouse_extract_monitor_rows_from_pages(array $pages): array
{
    $rowsById = [];

    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $items = $page['data'] ?? [];
        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $monitor) {
            if (!is_array($monitor)) {
                continue;
            }

            $monitorId = trim((string)($monitor['id'] ?? $monitor['monitorId'] ?? ''));
            if ($monitorId === '') {
                continue;
            }

            $rowsById[$monitorId] = [
                'monitor_id' => $monitorId,
                'name' => lighthouse_scalar_string_or_null($monitor['name'] ?? null),
                'url' => lighthouse_scalar_string_or_null($monitor['url'] ?? null),
                'version' => lighthouse_scalar_string_or_null($monitor['version'] ?? null),
                'region' => lighthouse_scalar_string_or_null($monitor['region'] ?? null),
                'device' => lighthouse_scalar_string_or_null($monitor['device'] ?? null),
                'headers_json' => lighthouse_json_encode_or_fail($monitor['headers'] ?? null),
                'updated_at_ms' => lighthouse_scalar_int_or_null($monitor['updatedAt'] ?? null),
                'created_at_ms' => lighthouse_scalar_int_or_null($monitor['createdAt'] ?? null)
            ];
        }
    }

    return array_values($rowsById);
}

function lighthouse_db_replace_monitors_from_pages(PDO $pdo, array $pages): int
{
    $rows = lighthouse_extract_monitor_rows_from_pages($pages);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM ' . LIGHTHOUSE_TABLE_MONITORS);
        if ($rows !== []) {
            $statement = $pdo->prepare(
                'INSERT INTO ' . LIGHTHOUSE_TABLE_MONITORS . ' (
                    monitor_id, name, url, version, region, device, headers_json, updated_at_ms, created_at_ms
                ) VALUES (
                    :monitor_id, :name, :url, :version, :region, :device, :headers_json, :updated_at_ms, :created_at_ms
                )'
            );

            foreach ($rows as $row) {
                $statement->execute($row);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        lighthouse_send_json(500, ['error' => 'Failed to store monitors in database', 'details' => $exception->getMessage()]);
    }

    return count($rows);
}

function lighthouse_db_get_monitor_ids(PDO $pdo): array
{
    $statement = $pdo->query('SELECT monitor_id FROM ' . LIGHTHOUSE_TABLE_MONITORS . ' ORDER BY monitor_id');
    $rows = $statement->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string)($row['monitor_id'] ?? '')),
        $rows
    ), static fn (string $id): bool => $id !== ''));
}

function lighthouse_extract_run_rows_from_responses(array $responses): array
{
    $rowsById = [];

    foreach ($responses as $responseItem) {
        if (!is_array($responseItem)) {
            continue;
        }

        $monitorId = trim((string)($responseItem['monitorId'] ?? ''));
        $apiStatus = (int)($responseItem['status'] ?? 0);
        $apiOk = (bool)($responseItem['ok'] ?? false);
        $apiError = lighthouse_scalar_string_or_null($responseItem['error'] ?? null);
        $items = $responseItem['data']['data'] ?? [];

        if (!is_array($items)) {
            continue;
        }

        foreach ($items as $run) {
            if (!is_array($run)) {
                continue;
            }

            $runId = trim((string)($run['id'] ?? $run['runId'] ?? ''));
            if ($runId === '') {
                continue;
            }

            $rowsById[$runId] = [
                'run_id' => $runId,
                'monitor_id' => $monitorId !== '' ? $monitorId : null,
                'region' => lighthouse_scalar_string_or_null($run['region'] ?? null),
                'state' => lighthouse_scalar_string_or_null($run['state'] ?? null),
                'created_at_ms' => lighthouse_scalar_int_or_null($run['createdAt'] ?? null),
                'api_status' => $apiStatus,
                'api_ok' => $apiOk ? 1 : 0,
                'api_error' => $apiError
            ];
        }
    }

    return array_values($rowsById);
}

function lighthouse_db_replace_runs_from_responses(PDO $pdo, array $responses): int
{
    $rows = lighthouse_extract_run_rows_from_responses($responses);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM ' . LIGHTHOUSE_TABLE_RUNS);
        if ($rows !== []) {
            $statement = $pdo->prepare(
                'INSERT INTO ' . LIGHTHOUSE_TABLE_RUNS . ' (
                    run_id, monitor_id, region, state, created_at_ms, api_status, api_ok, api_error
                ) VALUES (
                    :run_id, :monitor_id, :region, :state, :created_at_ms, :api_status, :api_ok, :api_error
                )'
            );

            foreach ($rows as $row) {
                $statement->execute($row);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        lighthouse_send_json(500, ['error' => 'Failed to store runs in database', 'details' => $exception->getMessage()]);
    }

    return count($rows);
}

function lighthouse_db_get_run_ids(PDO $pdo): array
{
    $statement = $pdo->query('SELECT run_id FROM ' . LIGHTHOUSE_TABLE_RUNS . ' ORDER BY created_at_ms DESC, run_id');
    $rows = $statement->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string)($row['run_id'] ?? '')),
        $rows
    ), static fn (string $id): bool => $id !== ''));
}

function lighthouse_extract_run_value_rows_from_responses(array $responses): array
{
    $rowsById = [];

    foreach ($responses as $responseItem) {
        if (!is_array($responseItem)) {
            continue;
        }

        $apiStatus = (int)($responseItem['status'] ?? 0);
        $apiOk = (bool)($responseItem['ok'] ?? false);
        $apiError = lighthouse_scalar_string_or_null($responseItem['error'] ?? null);
        $data = $responseItem['data'] ?? null;

        if (!is_array($data)) {
            continue;
        }

        $runId = trim((string)($data['id'] ?? $responseItem['runId'] ?? ''));
        if ($runId === '') {
            continue;
        }

        $rowsById[$runId] = [
            'run_id' => $runId,
            'version' => lighthouse_scalar_string_or_null($data['version'] ?? null),
            'url' => lighthouse_scalar_string_or_null($data['url'] ?? null),
            'device' => lighthouse_scalar_string_or_null($data['device'] ?? null),
            'region' => lighthouse_scalar_string_or_null($data['region'] ?? null),
            'state' => lighthouse_scalar_string_or_null($data['state'] ?? null),
            'performance' => lighthouse_scalar_float_or_null($data['performance'] ?? null),
            'accessibility' => lighthouse_scalar_float_or_null($data['accessibility'] ?? null),
            'best_practices' => lighthouse_scalar_float_or_null($data['bestPractices'] ?? null),
            'seo' => lighthouse_scalar_float_or_null($data['seo'] ?? null),
            'fcp' => lighthouse_scalar_float_or_null($data['fcp'] ?? null),
            'si' => lighthouse_scalar_float_or_null($data['si'] ?? null),
            'lcp' => lighthouse_scalar_float_or_null($data['lcp'] ?? null),
            'tti' => lighthouse_scalar_float_or_null($data['tti'] ?? null),
            'tbt' => lighthouse_scalar_float_or_null($data['tbt'] ?? null),
            'cls' => lighthouse_scalar_float_or_null($data['cls'] ?? null),
            'created_at_ms' => lighthouse_scalar_int_or_null($data['createdAt'] ?? null),
            'api_status' => $apiStatus,
            'api_ok' => $apiOk ? 1 : 0,
            'api_error' => $apiError
        ];
    }

    return array_values($rowsById);
}

function lighthouse_db_replace_run_values_from_responses(PDO $pdo, array $responses): int
{
    $rows = lighthouse_extract_run_value_rows_from_responses($responses);

    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM ' . LIGHTHOUSE_TABLE_RUN_VALUES);
        if ($rows !== []) {
            $statement = $pdo->prepare(
                'INSERT INTO ' . LIGHTHOUSE_TABLE_RUN_VALUES . ' (
                    run_id, version, url, device, region, state,
                    performance, accessibility, best_practices, seo,
                    fcp, si, lcp, tti, tbt, cls,
                    created_at_ms, api_status, api_ok, api_error
                ) VALUES (
                    :run_id, :version, :url, :device, :region, :state,
                    :performance, :accessibility, :best_practices, :seo,
                    :fcp, :si, :lcp, :tti, :tbt, :cls,
                    :created_at_ms, :api_status, :api_ok, :api_error
                )'
            );

            foreach ($rows as $row) {
                $statement->execute($row);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        lighthouse_send_json(500, ['error' => 'Failed to store run values in database', 'details' => $exception->getMessage()]);
    }

    return count($rows);
}

function lighthouse_db_prune_stale_run_values(PDO $pdo): int
{
    $statement = $pdo->prepare(
        'DELETE rv FROM ' . LIGHTHOUSE_TABLE_RUN_VALUES . ' rv
         WHERE NOT EXISTS (
             SELECT 1 FROM ' . LIGHTHOUSE_TABLE_RUNS . ' r WHERE r.run_id = rv.run_id
         )'
    );
    $statement->execute();

    return (int)$statement->rowCount();
}

function lighthouse_db_get_missing_run_value_ids(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT r.run_id FROM ' . LIGHTHOUSE_TABLE_RUNS . ' r
         WHERE NOT EXISTS (
             SELECT 1 FROM ' . LIGHTHOUSE_TABLE_RUN_VALUES . ' rv WHERE rv.run_id = r.run_id
         )
         ORDER BY r.created_at_ms DESC, r.run_id'
    );
    $rows = $statement->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string)($row['run_id'] ?? '')),
        $rows
    ), static fn (string $id): bool => $id !== ''));
}

function lighthouse_db_insert_run_values_from_responses(PDO $pdo, array $responses): int
{
    $rows = lighthouse_extract_run_value_rows_from_responses($responses);
    if ($rows === []) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT IGNORE INTO ' . LIGHTHOUSE_TABLE_RUN_VALUES . ' (
                run_id, version, url, device, region, state,
                performance, accessibility, best_practices, seo,
                fcp, si, lcp, tti, tbt, cls,
                created_at_ms, api_status, api_ok, api_error
            ) VALUES (
                :run_id, :version, :url, :device, :region, :state,
                :performance, :accessibility, :best_practices, :seo,
                :fcp, :si, :lcp, :tti, :tbt, :cls,
                :created_at_ms, :api_status, :api_ok, :api_error
            )'
        );

        foreach ($rows as $row) {
            $statement->execute($row);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        lighthouse_send_json(500, ['error' => 'Failed to insert run values into database', 'details' => $exception->getMessage()]);
    }

    return count($rows);
}
