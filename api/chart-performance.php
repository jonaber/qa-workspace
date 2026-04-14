<?php

declare(strict_types=1);

require __DIR__ . '/lighthouse-common.php';

lighthouse_assert_method(['GET']);

$settings = lighthouse_load_settings();
$pdo = lighthouse_db_initialize($settings);

$statement = $pdo->query(
    'SELECT url,
            ROUND(AVG(performance), 1) AS avg_performance,
            COUNT(*) AS run_count,
            GROUP_CONCAT(DISTINCT region ORDER BY region SEPARATOR \', \') AS regions
     FROM ' . LIGHTHOUSE_TABLE_RUN_VALUES . '
     WHERE url IS NOT NULL AND performance IS NOT NULL
       AND created_at_ms >= (UNIX_TIMESTAMP() - 7 * 86400) * 1000
     GROUP BY url
     ORDER BY avg_performance DESC'
);

$rows = $statement->fetchAll();

$labels = [];
$values = [];
$runCounts = [];
$regions = [];

foreach ($rows as $row) {
    $labels[] = (string)($row['url'] ?? '');
    $values[] = (float)$row['avg_performance'];
    $runCounts[] = (int)$row['run_count'];
    $regions[] = (string)($row['regions'] ?? '');
}

lighthouse_send_json(200, [
    'success' => true,
    'labels' => $labels,
    'values' => $values,
    'runCounts' => $runCounts,
    'regions' => $regions,
    'total' => count($rows)
]);
