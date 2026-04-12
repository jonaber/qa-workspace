<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['GET', 'POST']);

$startTime = microtime(true);

@set_time_limit(0);
@ini_set('max_execution_time', '0');
@ignore_user_abort(true);

$settings = lighthouse_load_settings();
$token = lighthouse_require_setting($settings, 'lighthouseApiToken');
$runValuesEndpointTemplate = lighthouse_require_setting($settings, 'lighthouseRunsValueEndpoint');
$pdo = lighthouse_db_initialize($settings);

$prunedCount = lighthouse_db_prune_stale_run_values($pdo);

$missingRunIds = lighthouse_filter_uuid_ids(lighthouse_db_get_missing_run_value_ids($pdo));

if ($missingRunIds === []) {
    lighthouse_send_json(200, [
        'success' => true,
        'message' => 'Run values are already up to date',
        'pruned' => $prunedCount,
        'fetched' => 0,
        'inserted' => 0,
        'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
        'table' => LIGHTHOUSE_TABLE_RUN_VALUES
    ]);
}

$runValuesPayload = [];

foreach ($missingRunIds as $runId) {
    $endpoint = str_replace(':runId', rawurlencode($runId), $runValuesEndpointTemplate);
    $response = lighthouse_http_get_json($endpoint, $token);

    $runValuesPayload[] = [
        'runId' => $runId,
        'status' => $response['status'] ?? 0,
        'ok' => $response['ok'] ?? false,
        'data' => $response['data'] ?? null,
        'error' => $response['error'] ?? null
    ];
}

$insertedCount = lighthouse_db_insert_run_values_from_responses($pdo, $runValuesPayload);

$successCalls = count(array_filter($runValuesPayload, static fn (array $item): bool => (bool)($item['ok'] ?? false)));

lighthouse_send_json(200, [
    'success' => true,
    'message' => 'Run values fetched and stored',
    'pruned' => $prunedCount,
    'fetched' => count($missingRunIds),
    'successfulCalls' => $successCalls,
    'inserted' => $insertedCount,
    'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
    'table' => LIGHTHOUSE_TABLE_RUN_VALUES
]);
