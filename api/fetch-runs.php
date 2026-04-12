<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['GET', 'POST']);

$startTime = microtime(true);

$settings = lighthouse_load_settings();
$token = lighthouse_require_setting($settings, 'lighthouseApiToken');
$runsEndpointTemplate = lighthouse_require_setting($settings, 'lighthouseRunsEndpoint');
$pdo = lighthouse_db_initialize($settings);

$monitorIds = lighthouse_db_get_monitor_ids($pdo);

if ($monitorIds === []) {
    lighthouse_send_json(400, ['error' => 'No monitor ids found in monitors dataset']);
}

$runsPayload = [];

foreach ($monitorIds as $monitorId) {
    $endpoint = str_replace(':monitorId', rawurlencode($monitorId), $runsEndpointTemplate);
    $response = lighthouse_http_get_json($endpoint, $token);

    $runsPayload[] = [
        'monitorId' => $monitorId,
        'status' => $response['status'] ?? 0,
        'ok' => $response['ok'] ?? false,
        'data' => $response['data'] ?? null,
        'error' => $response['error'] ?? null
    ];
}

$storedRuns = lighthouse_db_replace_runs_from_responses($pdo, $runsPayload);

$successCalls = count(array_filter($runsPayload, static fn (array $item): bool => (bool)($item['ok'] ?? false)));

lighthouse_send_json(200, [
    'success' => true,
    'message' => 'Runs fetched and stored',
    'monitorCalls' => count($monitorIds),
    'successfulCalls' => $successCalls,
    'runIdCount' => $storedRuns,
    'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
    'table' => LIGHTHOUSE_TABLE_RUNS
]);
