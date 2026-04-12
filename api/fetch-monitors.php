<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['GET', 'POST']);

$startTime = microtime(true);

function lighthouse_find_next_url(mixed $payload): ?string
{
    if (!is_array($payload)) {
        return null;
    }

    if (isset($payload['next']) && is_string($payload['next']) && trim($payload['next']) !== '') {
        return trim($payload['next']);
    }

    foreach ($payload as $value) {
        if (!is_array($value)) {
            continue;
        }

        $nestedNext = lighthouse_find_next_url($value);
        if ($nestedNext !== null) {
            return $nestedNext;
        }
    }

    return null;
}

$settings = lighthouse_load_settings();
$token = lighthouse_require_setting($settings, 'lighthouseApiToken');
$endpoint = lighthouse_require_setting($settings, 'lighthouseMonitorEndpoints');
$pdo = lighthouse_db_initialize($settings);

$pages = [];
$visitedUrls = [];
$nextUrl = $endpoint;
$maxPages = 100;

while ($nextUrl !== null && count($pages) < $maxPages) {
    $normalizedUrl = trim((string)(preg_replace('/\s+/', '', $nextUrl) ?? $nextUrl));
    if ($normalizedUrl === '') {
        break;
    }

    if (isset($visitedUrls[$normalizedUrl])) {
        lighthouse_send_json(502, [
            'error' => 'Pagination loop detected in monitors endpoint',
            'url' => $normalizedUrl
        ]);
    }

    $visitedUrls[$normalizedUrl] = true;

    $response = lighthouse_http_get_json($normalizedUrl, $token);
    if (!($response['ok'] ?? false)) {
        lighthouse_send_json(502, [
            'error' => 'Failed to fetch monitors',
            'status' => $response['status'] ?? 0,
            'details' => $response['error'] ?? 'Unknown error',
            'url' => $normalizedUrl
        ]);
    }

    $payload = $response['data'] ?? [];
    $pages[] = $payload;
    $nextUrl = lighthouse_find_next_url($payload);
}

if ($nextUrl !== null && count($pages) >= $maxPages) {
    lighthouse_send_json(502, [
        'error' => 'Pagination limit reached while fetching monitors',
        'limit' => $maxPages
    ]);
}

$monitorsPayload = [
    'pages' => $pages,
    'pageCount' => count($pages),
    'fetchedAt' => gmdate('c')
];

$storedMonitors = lighthouse_db_replace_monitors_from_pages($pdo, $pages);

$monitorIds = lighthouse_extract_ids_from_payload($monitorsPayload, ['monitorId', 'id']);

lighthouse_send_json(200, [
    'success' => true,
    'message' => 'Monitors fetched and stored',
    'pageCount' => count($pages),
    'monitorCount' => $storedMonitors > 0 ? $storedMonitors : count($monitorIds),
    'durationMs' => (int)round((microtime(true) - $startTime) * 1000),
    'table' => LIGHTHOUSE_TABLE_MONITORS
]);
