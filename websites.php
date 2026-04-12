<?php

$pageTitle = 'Websites - Lighthouse Metrics';
$activePage = 'websites';
$pageScripts = ['assets/js/websites.js'];

require __DIR__ . '/includes/layout-top.php';
?>
<section class="content-panel">
    <div class="cwv-panel">
        <h2>Monitored Websites</h2>
        <p>All unique URLs from your Lighthouse monitors.</p>

        <div class="websites-toolbar">
            <button type="button" id="export-csv" class="action-button" disabled>
                Export as CSV
            </button>
        </div>

        <p id="websites-status" class="status-message" aria-live="polite"></p>

        <div class="websites-table-wrapper">
            <table class="websites-table">
                <thead>
                    <tr>
                        <th class="websites-index-col">#</th>
                        <th>URL</th>
                        <th>Region</th>
                    </tr>
                </thead>
                <tbody id="websites-list">
                    <tr><td class="websites-empty" colspan="3">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-bottom.php';
