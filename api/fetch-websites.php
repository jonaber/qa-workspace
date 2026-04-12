<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['GET']);

$settings = lighthouse_load_settings();
$pdo = lighthouse_db_initialize($settings);

$stmt = $pdo->query(
    'SELECT DISTINCT url, region FROM ' . LIGHTHOUSE_TABLE_MONITORS . '
     WHERE url IS NOT NULL AND TRIM(url) != \'\'
     ORDER BY url ASC, region ASC'
);

$rows = $stmt->fetchAll();

$format = strtolower(trim($_GET['format'] ?? ''));

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="websites.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['URL', 'Region']);
    foreach ($rows as $row) {
        fputcsv($out, [(string)$row['url'], (string)($row['region'] ?? '')]);
    }
    fclose($out);
    exit;
}

lighthouse_send_json(200, [
    'success' => true,
    'count' => count($rows),
    'websites' => array_values($rows)
]);
