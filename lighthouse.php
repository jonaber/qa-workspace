<?php

$pageTitle = 'Core Web Vitals - Lighthouse Metrics';
$pageHeading = 'QA Workspace';
$activePage = 'core-web-vitals';
$pageScripts = ['assets/js/lighthouse.js'];
$pageExternalScripts = ['https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js'];

require __DIR__ . '/includes/layout-top.php';
?>
<section class="content-panel">
	<div class="cwv-panel">
		<h2>Data Collection</h2>
		<p>Run each step in order to fetch monitors, monitor runs, and run values.</p>

		<div class="cwv-actions">
			<button type="button" class="action-button" data-api-endpoint="api/fetch-monitors.php">
				1. Fetch Monitors
			</button>
			<button type="button" class="action-button" data-api-endpoint="api/fetch-runs.php">
				2. Fetch Runs by Monitor
			</button>
			<button type="button" class="action-button" data-api-endpoint="api/fetch-run-values.php">
				3. Fetch Run Values
			</button>
		</div>

		<p id="cwv-status" class="status-message" aria-live="polite"></p>
		<p id="last-fetch-timestamp" class="status-message"></p>
	</div>
</section>

<section class="content-panel chart-panel-section">
	<div class="cwv-panel">
		<h2>1 Week Average Performance by URL</h2>
		<p>Average Lighthouse performance score per monitored URL, calculated across run values from the last 7 days.</p>
		<div class="chart-wrapper">
			<canvas id="performance-chart" aria-label="Average performance score by URL"></canvas>
		</div>
		<p id="chart-status" class="status-message" aria-live="polite"></p>
	</div>
</section>
<?php require __DIR__ . '/includes/layout-bottom.php';
