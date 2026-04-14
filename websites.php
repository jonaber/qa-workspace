<?php

$pageTitle = 'Websites - Lighthouse Metrics';
$activePage = 'websites';
$pageScripts = ['assets/js/websites.js'];

require __DIR__ . '/includes/layout-top.php';
?>
<section class="content-panel">
    <div class="cwv-panel">
        <h2>Monitored Websites</h2>

        <div class="tab-nav" role="tablist">
            <button type="button" class="tab-button is-active" role="tab" aria-selected="true" data-tab="cwv">Core Web Vitals</button>
            <button type="button" class="tab-button" role="tab" aria-selected="false" data-tab="toplist">Toplist Check</button>
        </div>

        <div id="tab-cwv" class="tab-panel">
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

        <div id="tab-toplist" class="tab-panel is-hidden">
            <p>Toplist check URLs will appear here.</p>

            <div class="websites-table-wrapper">
                <table class="websites-table">
                    <thead>
                        <tr>
                            <th class="websites-index-col">#</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="websites-empty" colspan="2">No toplist URLs yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-bottom.php';
