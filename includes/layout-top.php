<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Lighthouse Metrics';
$pageHeading = $pageHeading ?? 'Lighthouse Metrics';
$activePage = $activePage ?? '';

$menuItems = [
    [
        'key' => 'home',
        'label' => 'Home',
        'href' => 'index.php'
    ],
    [
        'key' => 'websites',
        'label' => 'Websites',
        'href' => 'websites.php'
    ],
    [
        'key' => 'core-web-vitals',
        'label' => 'Core Web Vitals',
        'href' => 'lighthouse.php'
    ],
    [
        'key' => 'crux-details',
        'label' => 'CRUX Details',
        'href' => 'crux-details.php'
    ],
    [
        'key' => 'risk-monitoring',
        'label' => 'Risk Monitoring Report',
        'href' => 'risk-monitoring.php'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body>
    <header class="site-header">
        <h1>QA Workspace</h1>
        <a class="icon-button" href="settings.php" aria-label="Open settings" title="Settings">⚙</a>
    </header>

    <div class="app-shell">
        <nav class="side-menu" aria-label="Primary">
            <ul class="menu-list">
                <?php foreach ($menuItems as $item): ?>
                    <li>
                        <a
                            class="menu-link<?php echo $activePage === $item['key'] ? ' is-active' : ''; ?>"
                            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <main class="main-content">
