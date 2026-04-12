<?php

declare(strict_types=1);

header('Content-Type: application/json');

$settingsFile = __DIR__ . '/../settings.json';
$defaultSettings = [
    'lighthouseApiToken' => '',
    'lighthouseMonitorEndpoints' => '',
    'lighthouseRunsEndpoint' => '',
    'lighthouseRunsValueEndpoint' => '',
    'dbHost' => '127.0.0.1',
    'dbPort' => 3306,
    'dbName' => 'lighthouse',
    'dbUser' => 'root',
    'dbPassword' => '',
    'scoreAmber' => 50,
    'scoreRed' => 25
];

function sendResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $raw = file_get_contents($settingsFile);
    $decoded = json_decode($raw ?: '', true);

    if (!is_array($decoded)) {
        sendResponse(500, ['error' => 'Failed to parse settings.json']);
    }

    $merged = array_merge($defaultSettings, $decoded);
    $publicSettings = [
        'lighthouseApiToken' => (string)($merged['lighthouseApiToken'] ?? ''),
        'lighthouseMonitorEndpoints' => (string)($merged['lighthouseMonitorEndpoints'] ?? ''),
        'lighthouseRunsEndpoint' => (string)($merged['lighthouseRunsEndpoint'] ?? ''),
        'lighthouseRunsValueEndpoint' => (string)($merged['lighthouseRunsValueEndpoint'] ?? ''),
        'scoreAmber' => (int)($merged['scoreAmber'] ?? 50),
        'scoreRed' => (int)($merged['scoreRed'] ?? 25)
    ];

    sendResponse(200, $publicSettings);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $decodedInput = json_decode($rawInput ?: '', true);

    if (!is_array($decodedInput)) {
        sendResponse(400, ['error' => 'Invalid JSON payload']);
    }

    $monitorEndpoint = $decodedInput['lighthouseMonitorEndpoints'] ?? '';
    if (is_array($monitorEndpoint)) {
        $monitorEndpoint = (string)($monitorEndpoint[0] ?? '');
    }

    if (!is_string($monitorEndpoint)) {
        sendResponse(400, ['error' => 'lighthouseMonitorEndpoints must be a string']);
    }

    $currentRaw = file_get_contents($settingsFile);
    $currentDecoded = json_decode($currentRaw ?: '', true);
    if (!is_array($currentDecoded)) {
        $currentDecoded = [];
    }

    $scoreAmber = (int)($decodedInput['scoreAmber'] ?? $currentDecoded['scoreAmber'] ?? 50);
    $scoreRed   = (int)($decodedInput['scoreRed']   ?? $currentDecoded['scoreRed']   ?? 25);

    if ($scoreAmber < 0 || $scoreAmber > 100) {
        sendResponse(400, ['error' => 'scoreAmber must be between 0 and 100']);
    }

    if ($scoreRed < 0 || $scoreRed > 100) {
        sendResponse(400, ['error' => 'scoreRed must be between 0 and 100']);
    }

    if ($scoreRed >= $scoreAmber) {
        sendResponse(400, ['error' => 'scoreRed must be less than scoreAmber']);
    }

    $settings = array_merge($defaultSettings, $currentDecoded, [
        'lighthouseApiToken' => (string)($decodedInput['lighthouseApiToken'] ?? ''),
        'lighthouseMonitorEndpoints' => trim($monitorEndpoint),
        'lighthouseRunsEndpoint' => (string)($decodedInput['lighthouseRunsEndpoint'] ?? ''),
        'lighthouseRunsValueEndpoint' => (string)($decodedInput['lighthouseRunsValueEndpoint'] ?? ''),
        'scoreAmber' => $scoreAmber,
        'scoreRed' => $scoreRed
    ]);

    $settings['dbPort'] = (int)($settings['dbPort'] ?? 3306);
    if ($settings['dbPort'] <= 0 || $settings['dbPort'] > 65535) {
        sendResponse(400, ['error' => 'settings.json contains invalid dbPort']);
    }

    $saved = file_put_contents(
        $settingsFile,
        json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    if ($saved === false) {
        sendResponse(500, ['error' => 'Failed to write settings.json']);
    }

    sendResponse(200, ['success' => true]);
}

sendResponse(405, ['error' => 'Method not allowed']);
