<?php

declare(strict_types=1);

$externalDir = __DIR__ . '/external';

// Find the latest risk monitoring report file (pattern: money_sites_risk_monitoring_report_YYYYMMDD_HHMMSS.html)
$latestFile = null;
$latestName = null;

if (is_dir($externalDir)) {
    $files = glob($externalDir . '/money_sites_risk_monitoring_report_*.html');
    if (is_array($files) && count($files) > 0) {
        usort($files, static fn(string $a, string $b): int => strcmp(basename($b), basename($a)));
        $candidate = $files[0];
        $basename = basename($candidate);
        if (preg_match('/^money_sites_risk_monitoring_report_\d{8}_\d{6}\.html$/', $basename) === 1) {
            $latestFile = $candidate;
            $latestName = $basename;
        }
    }
}

$pageTitle = 'Risk Monitoring Report - Lighthouse Metrics';
$activePage = 'risk-monitoring';

require __DIR__ . '/includes/layout-top.php';
?>
<section class="content-panel crux-panel">
    <div class="cwv-panel">
        <div class="crux-header">
            <h2>Risk Monitoring Report</h2>
            <?php if ($latestName !== null): ?>
                <span class="crux-filename"><?php echo htmlspecialchars($latestName, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($latestFile === null): ?>
            <p class="crux-empty">No risk monitoring report found in the <code>external/</code> folder.<br>
            Add a file matching <code>money_sites_risk_monitoring_report_YYYYMMDD_HHMMSS.html</code> and reload.</p>
        <?php else: ?>
            <div class="crux-iframe-wrapper">
                <iframe
                    src="external/<?php echo htmlspecialchars($latestName, ENT_QUOTES, 'UTF-8'); ?>"
                    class="crux-iframe"
                    title="Risk Monitoring Report"
                    loading="lazy"
                ></iframe>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-bottom.php';
