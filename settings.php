<?php

$pageTitle = 'Settings - Lighthouse Metrics';
$pageHeading = 'Settings';
$activePage = '';
$pageScripts = ['assets/js/settings.js'];

require __DIR__ . '/includes/layout-top.php';
?>
<section class="content-panel">
    <div class="cwv-panel">
        <h2>Settings</h2>
        <form id="settings-form" class="settings-form">
    <label for="lighthouseApiToken">Lighthouse API token</label>
    <input id="lighthouseApiToken" name="lighthouseApiToken" type="password" autocomplete="off" />

    <label for="lighthouseMonitorEndpoints">Lighthouse monitor endpoints</label>
    <input id="lighthouseMonitorEndpoints" name="lighthouseMonitorEndpoints" type="text" />

    <label for="lighthouseRunsEndpoint">Lighthouse runs endpoint</label>
    <input id="lighthouseRunsEndpoint" name="lighthouseRunsEndpoint" type="text" />

    <label for="lighthouseRunsValueEndpoint">Lighthouse runs value endpoint</label>
    <input id="lighthouseRunsValueEndpoint" name="lighthouseRunsValueEndpoint" type="text" />

    <fieldset class="settings-fieldset">
        <legend>Performance Score Thresholds</legend>
        <p class="settings-fieldset-hint">Scores above the amber threshold are green. Scores at or below amber but above red are amber. Scores at or below the red threshold are red.</p>
        <label for="scoreAmber">Amber threshold (0–100)</label>
        <input id="scoreAmber" name="scoreAmber" type="number" min="0" max="100" placeholder="50" />
        <label for="scoreRed">Red threshold (0–100)</label>
        <input id="scoreRed" name="scoreRed" type="number" min="0" max="100" placeholder="25" />
    </fieldset>

    <button type="submit">Save settings</button>
    <p id="status-message" class="status-message" aria-live="polite"></p>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-bottom.php';
